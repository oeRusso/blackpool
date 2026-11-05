# Despliegue en un VPS (Contabo) con Cloudflare

Guía paso a paso para publicar BlackPool en un VPS con Docker, detrás de Cloudflare (plan gratuito). Todo lo que se usa es gratuito u open source. El único coste es el VPS y el dominio.

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

```bash
sudo mkdir -p /srv/blackpool && sudo chown deploy: /srv/blackpool
git clone https://github.com/<tu-usuario>/blackpool.git /srv/blackpool
cd /srv/blackpool
cp .env.production.example .env
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'   # → APP_KEY
nano .env   # APP_URL, contraseñas de BD, SMTP, Backblaze…
```

Variables importantes:

| Variable                                       | Para qué                                                                           |
| ---------------------------------------------- | ---------------------------------------------------------------------------------- |
| `APP_URL`                                      | URL pública con `https://`                                                         |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`              | Contraseñas de MySQL (genera unas largas)                                          |
| `MEDIA_ACCEL_REDIRECT_PREFIX=/protected-media` | Nginx sirve los vídeos tras autorizar en Laravel                                   |
| `INERTIA_SSR_URL=http://ssr:13714`             | Contenedor que pre-renderiza las páginas públicas (SEO)                            |
| `INERTIA_SSR_ENSURE_BUNDLE_EXISTS=false`       | El bundle de SSR vive en el contenedor `ssr`, no en `app`                          |
| `MEDIA_CHUNK_SIZE`                             | Tamaño de cada trozo de subida (8 MB; debe ser menor que los 100 MB de Cloudflare) |
| `REDIS_QUEUE_RETRY_AFTER=3660`                 | Debe superar el timeout del job de ffmpeg (3600 s). Hay un test que lo verifica.   |
| `BACKUP_*`                                     | Bucket de Backblaze B2 para los backups                                            |

## 3. Cloudflare

1. **DNS:** registro `A` del dominio (o subdominio) apuntando a la IP del VPS, con proxy activado (nube naranja).
2. **SSL/TLS → Overview:** modo **Full (strict)**.
3. **SSL/TLS → Origin Server → Create certificate** (15 años, gratis). Guarda el certificado en `docker/production/certs/origin.pem` y la clave privada en `docker/production/certs/origin.key`. La carpeta está en `.gitignore`.
4. **SSL/TLS → Edge Certificates:** activa _Always Use HTTPS_ y _HSTS_ cuando todo funcione.
5. **Security → WAF → Rate limiting rules (gratis):** por ejemplo, 20 peticiones cada 10 s por IP a `/login`.
6. **Caching → Cache Rules:** _Cache everything_ para `/build/*`, que son assets con hash e inmutables.
7. **Vídeo:** los segmentos HLS pasan por el proxy porque Laravel los autoriza en cada petición. Antes de servir mucho tráfico de vídeo, revisa los términos del plan gratuito de Cloudflare sobre contenido multimedia. Las alternativas gratuitas son:
    - servir los medios desde un subdominio en modo _DNS only_ (nube gris), o
    - mover el disco `media` a Backblaze B2, cuyo tráfico de salida hacia Cloudflare es gratuito.

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

## 6. Backups (Backblaze B2, 10 GB gratis)

1. Crea un bucket privado y una _Application Key_ con acceso solo a ese bucket.
2. Rellena `BACKUP_S3_KEY`, `BACKUP_S3_SECRET`, `BACKUP_S3_BUCKET` y el endpoint de tu región.
3. El scheduler ejecuta cada noche `backup:clean` y `backup:run` (volcado de MySQL, portadas y PDFs), y `backup:monitor` avisa por email si el último backup tiene más de un día.
4. Prueba manual:

    ```bash
    docker compose -f compose.production.yaml exec app php artisan backup:run
    docker compose -f compose.production.yaml exec app php artisan backup:list
    ```

**Restaurar:** descarga el zip, importa `db-dumps/mysql-*.sql` con `mysql` y copia `storage/app/public` y `storage/app/private/lesson-pdfs` al volumen `storage`.

## 7. Monitorización gratuita

- **Laravel Pulse** en `/pulse` (solo administradores): peticiones lentas, jobs, excepciones y uso de caché.
- **Uptime Kuma** (autoalojado) o **UptimeRobot** (gratis): monitoriza `https://tu-dominio/up`.
- Logs: `docker compose -f compose.production.yaml logs -f app worker`.
- Errores: opcionalmente, GlitchTip autoalojado o el plan gratuito de Sentry.

## 8. SEO

- `https://tu-dominio/sitemap.xml`: lecciones publicadas y páginas por nivel. Envíalo en Google Search Console y Bing Webmaster Tools (ambos gratuitos).
- `robots.txt` solo permite indexar cuando `APP_ENV=production`.
- El contenedor `ssr` no publica puertos. Si se cae, las páginas se renderizan en el navegador; compruébalo con `docker compose -f compose.production.yaml ps ssr`.

## 9. Verificación local del stack de producción

El mismo `compose.production.yaml` se puede levantar en local sin TLS. Así se ha validado este repositorio: los 60 tests de Playwright pasan contra Nginx, PHP-FPM, MySQL y Redis.

```bash
cp .env.production.example .env.prodtest   # APP_KEY, APP_URL=http://127.0.0.1:8020, SESSION_SECURE_COOKIE=false…
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
