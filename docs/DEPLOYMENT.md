# Despliegue en un VPS (Contabo) con Cloudflare

Guía paso a paso para publicar BlackPool en un VPS con Docker, detrás de Cloudflare (plan gratuito). Todo lo que se usa es gratuito u open source y está dentro del plan gratuito de Cloudflare. El único coste es el VPS y el dominio, que ya existen.

```
Navegador ──HTTPS──▶ Cloudflare (DNS, proxy, WAF, caché)
                         │  HTTPS con Origin Certificate (modo Full strict)
                         ▼
                   VPS · docker compose
   ┌──────────┐  FastCGI  ┌──────────┐   ┌────────┐   ┌───────┐
   │  nginx   │──────────▶│ php-fpm  │──▶│ MySQL  │   │ Redis │
   │ (web)    │◀─X-Accel──│  (app)   │   └────────┘   └───────┘
   └──────────┘           └──────────┘        ▲            ▲
                               │ HTML de páginas públicas
                               ▼
                          ┌──────────┐
                          │   ssr    │ (Node, solo red interna)
                          └──────────┘
        │ lee /storage (solo lectura)          │            │
        ▼                 ┌──────────┐   ┌───────────┐     │
   volumen storage ◀──────│  worker  │   │ scheduler │─────┘
   (portadas, PDFs, HLS)  │ (ffmpeg) │   │ (cron)    │
                          └──────────┘   └───────────┘
```

## 1. Preparar el VPS (Ubuntu 24.04 LTS)

```bash
# Usuario sin privilegios para desplegar
adduser deploy && usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# SSH solo con clave
sudo sed -i 's/^#\?PasswordAuthentication .*/PasswordAuthentication no/; s/^#\?PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
sudo systemctl restart ssh

# Firewall: solo SSH, HTTP y HTTPS
sudo ufw allow OpenSSH && sudo ufw allow 80,443/tcp && sudo ufw enable

# Fail2ban contra fuerza bruta en SSH
sudo apt install -y fail2ban && sudo systemctl enable --now fail2ban

# Docker Engine + Compose plugin
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy
```

> Opcional: para que solo Cloudflare pueda llegar a los puertos 80/443, limita esas reglas de `ufw` a los rangos de Cloudflare (https://www.cloudflare.com/ips/).

## 2. Clonar y configurar

El repositorio no contiene ningún archivo `.env`, tampoco plantillas. El `.env` de producción se crea **solo en el servidor**, con permisos `600`, y nunca sale de él.

```bash
sudo mkdir -p /srv/blackpool && sudo chown deploy: /srv/blackpool
git clone https://github.com/<tu-usuario>/blackpool.git /srv/blackpool
cd /srv/blackpool
touch .env && chmod 600 .env && nano .env
```

Variables que debe tener (los secretos se generan con `openssl rand -base64 32`):

| Variable                                                          | Valor / para qué                                                               |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `APP_NAME`, `VITE_APP_NAME`                                       | `BlackPool`                                                                    |
| `APP_ENV`, `APP_DEBUG`                                            | `production`, `false`                                                          |
| `APP_KEY`                                                         | `base64:` + 32 bytes aleatorios                                                |
| `APP_URL`                                                         | URL pública con `https://`, p. ej. `https://blackpool.tudominio.com`           |
| `APP_REPOSITORY_URL`                                              | Enlace al código en el pie de página (opcional)                                |
| `LOG_CHANNEL`, `LOG_LEVEL`                                        | `stderr`, `warning`                                                            |
| `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`          | `mysql`, `mysql`, `blackpool`, `blackpool`                                     |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`                                 | Contraseñas largas y aleatorias                                                |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST` | `redis`, `redis`, `redis`, `redis`                                             |
| `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`                        | `true`, `true`                                                                 |
| `REDIS_QUEUE_RETRY_AFTER`                                         | `3660`: debe superar el timeout del job de ffmpeg (3600 s). Un test lo vigila. |
| `INERTIA_SSR_URL`, `INERTIA_SSR_ENSURE_BUNDLE_EXISTS`             | `http://ssr:13714`, `false` (el bundle de SSR vive en el contenedor `ssr`)     |
| `MEDIA_ACCEL_REDIRECT_PREFIX`                                     | `/protected-media`: Nginx sirve los vídeos tras autorizar en Laravel           |
| `MEDIA_CHUNK_SIZE`                                                | `8388608` (8 MB, por debajo del límite de 100 MB de Cloudflare)                |
| `SECURITY_CSP_ENABLED`, `SECURITY_HSTS`                           | `true`, `true`                                                                 |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`                                | `log` (sin servicio de correo externo) y una dirección de remitente            |
| `PULSE_ENABLED`, `DEMO_MODE`                                      | `true`, `true`                                                                 |

> Con `MAIL_MAILER=log` no se envían correos (verificación, recuperar contraseña): quedan en los logs. Las cuentas de demo ya están verificadas. Si más adelante quieres enviar correos, basta con un SMTP de plan gratuito sin tarjeta, cambiando `MAIL_MAILER=smtp` y sus credenciales.

## 3. Cloudflare

Todo lo de esta sección está incluido en el **plan Free** de Cloudflare. No actives productos de pago (Advanced Certificate Manager, Argo, Load Balancing, Images, Stream…). Por eso el proyecto usa un subdominio de un solo nivel (`blackpool.tudominio.com`): el certificado gratuito cubre `*.tudominio.com`, pero no `*.proyectos.tudominio.com`.

1. **DNS:** registro `A` `blackpool` apuntando a la IP del VPS, con proxy activado (nube naranja).
2. **SSL/TLS → Overview:** modo **Full (strict)**.
3. **SSL/TLS → Origin Server → Create certificate** (15 años, gratis). Guarda el certificado en `docker/production/certs/origin.pem` y la clave privada en `docker/production/certs/origin.key`. La carpeta está en `.gitignore`.
4. **SSL/TLS → Edge Certificates:** activa _Always Use HTTPS_ y _HSTS_ cuando todo funcione.
5. **Security → WAF → Rate limiting rules:** el plan Free incluye una regla; por ejemplo, 20 peticiones cada 10 s por IP a `/login`. Laravel ya limita el login por su cuenta, así que es opcional.
6. **Caching → Cache Rules:** _Cache everything_ para `/build/*`, que son assets con hash e inmutables.
7. **Vídeo:** los segmentos HLS pasan por el proxy porque Laravel los autoriza en cada petición. Para el tráfico de un portfolio es suficiente. Si algún día hubiera mucho tráfico de vídeo, la alternativa gratuita es servir los medios desde un subdominio en modo _DNS only_ (nube gris).

La IP real del visitante se recupera con `CF-Connecting-IP` (ver `docker/production/nginx/cloudflare.conf`). Para actualizar los rangos de IP de Cloudflare, ejecuta `docker/production/update-cloudflare-ips.sh`.

## 4. Primer arranque

```bash
docker compose -f compose.production.yaml up -d --build --wait
docker compose -f compose.production.yaml exec app php artisan db:seed --force   # datos y cuentas de demo
curl -I https://tu-dominio/up
```

Las migraciones se ejecutan solas al arrancar el contenedor `app` (`RUN_MIGRATIONS=true`). En producción, `migrate:fresh` y `db:wipe` están bloqueados (`DB::prohibitDestructiveCommands`).

## 5. Despliegue continuo (GitHub Actions)

`.github/workflows/deploy.yml` despliega `main` cuando el CI pasa: entra por SSH y ejecuta `bin/deploy` (`git pull`, build, `up --wait`, `queue:restart`).

Configura en **Settings → Secrets and variables → Actions**:

- Secrets: `SSH_HOST`, `SSH_USER` (deploy), `SSH_PRIVATE_KEY` (una clave dedicada, añadida a `~deploy/.ssh/authorized_keys`) y `DEPLOY_PATH` (`/srv/blackpool`).
- Variable: `APP_URL`, para el smoke test posterior al despliegue.

## 6. Backups (en el propio VPS, sin servicios externos)

1. El scheduler ejecuta cada noche `backup:clean` y `backup:run` (volcado de MySQL, portadas y PDFs). `backup:monitor` deja un aviso si el último backup tiene más de un día.
2. Los backups quedan en el volumen `storage` (`storage/app/private/BlackPool`), con rotación: 7 días completos, luego diarios y semanales. Nunca ocupan más de 1,5 GB.
3. Para tener una copia fuera del servidor sin coste, descárgala de vez en cuando a tu PC:

    ```bash
    docker compose -f compose.production.yaml exec app php artisan backup:list
    docker compose -f compose.production.yaml cp app:/var/www/html/storage/app/private/BlackPool ./backups
    scp -r deploy@IP_DEL_VPS:/srv/blackpool/backups ~/blackpool-backups
    ```

4. Prueba manual:

    ```bash
    docker compose -f compose.production.yaml exec app php artisan backup:run
    docker compose -f compose.production.yaml exec app php artisan backup:list
    ```

**Restaurar:** descarga el zip, importa `db-dumps/mysql-*.sql` con `mysql` y copia `storage/app/public` y `storage/app/private/lesson-pdfs` al volumen `storage`.

## 7. Monitorización gratuita

- **Laravel Pulse** en `/pulse` (solo administradores): peticiones lentas, jobs, excepciones y uso de caché.
- Logs: `docker compose -f compose.production.yaml logs -f app worker`.
- Disponibilidad: `curl -fsS https://tu-dominio/up` (el workflow de deploy lo comprueba tras cada despliegue).

## 8. SEO

- `https://tu-dominio/sitemap.xml`: lecciones publicadas y páginas por nivel. Envíalo en Google Search Console y Bing Webmaster Tools (ambos gratuitos).
- `robots.txt` solo permite indexar cuando `APP_ENV=production`.
- El contenedor `ssr` no publica puertos. Si se cae, las páginas se renderizan en el navegador; compruébalo con `docker compose -f compose.production.yaml ps ssr`.

## 9. Verificación local del stack de producción

El mismo `compose.production.yaml` se puede levantar en local sin TLS. Así se ha validado este repositorio: los 71 tests de Playwright pasan contra Nginx, PHP-FPM, SSR, MySQL y Redis.

```bash
# .env.prodtest (local, nunca en git): las variables de la sección 2 con
# APP_URL=http://127.0.0.1:8020, SESSION_SECURE_COOKIE=false y SECURITY_HSTS=false.
export APP_ENV_FILE=.env.prodtest NGINX_CONF=./docker/production/nginx/local.conf HTTP_PORT=8020 HTTPS_PORT=8443
set -a; . ./.env.prodtest; set +a
docker compose -p blackpool-prodtest -f compose.production.yaml up -d --build --wait
docker compose -p blackpool-prodtest -f compose.production.yaml exec app php artisan db:seed --force
E2E_BASE_URL=http://127.0.0.1:8020 bin/e2e
```

Problemas que detectó esta prueba y que ya están corregidos:

- **502 de Nginx:** las cabeceras de precarga (`Link`), la CSP y las cookies superaban el buffer FastCGI por defecto. Se ha aumentado `fastcgi_buffer_size`.
- **403 al servir vídeos:** Nginx (otro usuario) no podía leer los directorios del disco `media`, que se creaban con permisos 0700. Ahora se crean con permisos 0755/0644.
- **Cola:** `retry_after` (90 s) era menor que el timeout de ffmpeg (3600 s), lo que habría duplicado transcodificaciones largas.
- **Seeders:** no eran idempotentes y duplicaban lecciones al resembrar.
