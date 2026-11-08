# Despliegue en el VPS compartido (Contabo + Cloudflare Tunnel)

BlackPool vive en el mismo VPS que el portfolio de Esteban y sigue sus reglas (`PORTAFOLIO2.0/docs/VPS-COMPARTIDO.md`):

- **Costo cero extra.** Solo el VPS de Contabo y el plan Free de Cloudflare, sin complementos. Ningún SaaS: MySQL, Redis, cola, SSR y ffmpeg corren en contenedores del propio VPS.
- **Ningún puerto abierto a internet.** El firewall solo deja pasar SSH. El tráfico web entra por un **Cloudflare Tunnel** propio del proyecto, que es una conexión saliente.
- **En Cloudflare solo DNS y el túnel**, que crea Esteban. Nada de certificados de origen, reglas de WAF ni de caché.
- **Secretos fuera del chat y de git.** El `.env` existe solo en el VPS, con permisos `600`. El repositorio no contiene ningún `.env` ni plantillas.
- **`sudo` lo corre Esteban.** El agente le pasa el comando exacto.
- **Se despliega `main`.** Se trabaja en `development`, se abre un PR y Esteban lo mergea.

| Dato                     | Valor                                             |
| ------------------------ | ------------------------------------------------- |
| Dirección pública        | `https://blackpool.esteban-russo.com`             |
| Túnel (Cloudflare)       | `blackpool`                                       |
| Ruta del túnel           | `http://web:80` (Nginx del compose)               |
| Carpeta en el VPS        | `~/blackpool` (usuario `esteban`)                 |
| Puertos usados en el VPS | ninguno (el MySQL del proyecto no publica puerto) |

```
Navegador ──HTTPS──▶ Cloudflare ◀──túnel saliente── cloudflared (contenedor `tunnel`)
                                                        │ HTTP, red interna de Docker
                                                        ▼
   ┌──────────┐  FastCGI  ┌──────────┐   ┌────────┐   ┌───────┐
   │  nginx   │──────────▶│ php-fpm  │──▶│ MySQL  │   │ Redis │
   │  (web)   │◀─X-Accel──│  (app)   │   └────────┘   └───────┘
   └──────────┘           └──────────┘        ▲            ▲
        │                      │ HTML de páginas públicas  │
        │                      ▼                           │
        │                 ┌──────────┐                     │
        │                 │   ssr    │ (Node)              │
        │                 └──────────┘                     │
        │ lee /storage         ┌──────────┐   ┌───────────┐│
        ▼ (solo lectura)       │  worker  │   │ scheduler │┘
   volumen storage ◀───────────│ (ffmpeg) │   │  (cron)   │
                               └──────────┘   └───────────┘
```

**HTTPS y la IP del visitante.** Cloudflare termina el HTTPS. Nginx solo recibe conexiones de `cloudflared` por la red interna y hace dos cosas:

- recupera la IP real del visitante desde `CF-Connecting-IP` (`docker/production/nginx/realip.conf`);
- pasa a PHP el esquema original a partir de `X-Forwarded-Proto`, para que Laravel genere URLs `https://` y cookies `Secure`.

**Recursos.** Todos los servicios tienen `mem_limit`: suman como máximo unos 3 GB, aunque en reposo usan unos 400 MB. El worker transcodifica un vídeo a la vez y ffmpeg usa como mucho 2 de los 4 núcleos.

## 1. Crear el túnel (Esteban, en Cloudflare)

1. **Networking → Tunnels → Create a tunnel → Cloudflared**, con el nombre `blackpool` y entorno **Docker**.
2. Copia **solo el token**, que es lo que va después de `--token`. No lo pegues en el chat.
3. _Continue_ se habilita cuando el túnel se conecta una vez. Puedes correr en tu PC el `docker run` que muestra la página, esperar a "Connected" y cortarlo con Ctrl+C.
4. En la pestaña **Routes**: _Add route_ → **Published application**, con subdominio `blackpool`, dominio `esteban-russo.com` y URL del servicio `http://web:80`. Cloudflare crea el registro DNS solo.

## 2. Clonar en el VPS

```bash
ssh esteban@164.68.121.27
git clone -b main https://github.com/oeRusso/blackpool.git ~/blackpool
```

Si el repositorio es privado, usa una deploy key de solo lectura generada en el VPS, como indica la guía del VPS compartido.

## 3. `.env` de producción (solo en el VPS)

El agente lo genera en el VPS con contraseñas aleatorias (`openssl rand`), sin mostrarlas, y con `chmod 600`. Contiene:

| Variable                                                          | Valor / para qué                                                               |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `APP_NAME`, `VITE_APP_NAME`                                       | `BlackPool`                                                                    |
| `APP_ENV`, `APP_DEBUG`                                            | `production`, `false`                                                          |
| `APP_KEY`                                                         | `base64:` + 32 bytes aleatorios                                                |
| `APP_URL`                                                         | `https://blackpool.esteban-russo.com`                                          |
| `APP_REPOSITORY_URL`                                              | Enlace al código en el pie de página (opcional)                                |
| `LOG_CHANNEL`, `LOG_LEVEL`                                        | `stderr`, `warning`                                                            |
| `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`          | `mysql`, `mysql`, `blackpool`, `blackpool`                                     |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`                                 | Aleatorias (`openssl rand -hex 32`)                                            |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST` | `redis`, `redis`, `redis`, `redis`                                             |
| `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`                        | `true`, `true`                                                                 |
| `REDIS_QUEUE_RETRY_AFTER`                                         | `3660`: debe superar el timeout del job de ffmpeg (3600 s). Un test lo vigila. |
| `INERTIA_SSR_URL`, `INERTIA_SSR_ENSURE_BUNDLE_EXISTS`             | `http://ssr:13714`, `false` (el bundle de SSR vive en el contenedor `ssr`)     |
| `MEDIA_ACCEL_REDIRECT_PREFIX`                                     | `/protected-media`: Nginx sirve los vídeos tras autorizar en Laravel           |
| `MEDIA_CHUNK_SIZE`                                                | `8388608` (8 MB, por debajo del límite de 100 MB de Cloudflare)                |
| `SECURITY_CSP_ENABLED`, `SECURITY_HSTS`                           | `true`, `true`                                                                 |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`                                | `log` (sin servicio de correo) y una dirección de remitente                    |
| `PULSE_ENABLED`, `DEMO_MODE`                                      | `true`, `true`                                                                 |
| `TUNNEL_TOKEN`                                                    | Lo pega Esteban, sin que se vea (ver abajo)                                    |

> Con `MAIL_MAILER=log` no se envía ningún correo: los de verificación y recuperación de contraseña quedan en los logs. Las cuentas de demo ya están verificadas.

Esteban pega el token del túnel sin que aparezca en pantalla:

```bash
read -rsp "Pegá el TUNNEL_TOKEN y Enter: " T && sed -i "s|^TUNNEL_TOKEN=.*|TUNNEL_TOKEN=$T|" ~/blackpool/.env && unset T && grep -c '^TUNNEL_TOKEN=eyJ' ~/blackpool/.env
```

Debe responder `1`.

## 4. Primer arranque

Antes, comprueba que hay memoria libre con `free -h` y `docker stats --no-stream`. Deja siempre ~1 GB libre para el sistema.

```bash
cd ~/blackpool
docker compose up -d --build --wait
docker compose exec app php artisan db:seed --force   # datos y cuentas de demo
docker compose ps                                     # todo "healthy"
curl -I https://blackpool.esteban-russo.com/up
```

Las migraciones se ejecutan solas al arrancar el contenedor `app`. En producción, `migrate:fresh` y `db:wipe` están bloqueados (`DB::prohibitDestructiveCommands`).

## 5. Actualizar

Después de que Esteban mergee el PR a `main`:

```bash
cd ~/blackpool && bin/deploy
```

`bin/deploy` ejecuta `git pull --ff-only`, reconstruye las imágenes, levanta el stack con `--wait`, reinicia la cola y limpia las imágenes viejas. No hay despliegue automático: ningún servicio externo tiene una clave SSH del VPS. GitHub Actions solo ejecuta el CI (tests y análisis).

## 6. Backups (en el propio VPS)

1. El scheduler ejecuta cada noche `backup:clean` y `backup:run` (volcado de MySQL, portadas y PDFs). `backup:monitor` deja un aviso si el último backup tiene más de un día.
2. Los backups quedan en el volumen `storage` (`storage/app/private/BlackPool`), con rotación: 7 días completos, luego diarios y semanales. Nunca ocupan más de 1,5 GB.
3. Para tener una copia fuera del servidor sin coste, descárgala de vez en cuando a tu PC:

    ```bash
    # en el VPS
    cd ~/blackpool && docker compose cp app:/var/www/html/storage/app/private/BlackPool ./backups
    # en tu PC
    scp -r esteban@164.68.121.27:blackpool/backups ~/blackpool-backups
    ```

**Restaurar:** importa `db-dumps/mysql-*.sql` del zip con `mysql`, y copia `storage/app/public` y `storage/app/private/lesson-pdfs` al volumen `storage`.

## 7. Verificación local del stack de producción

`compose.prodtest.yaml` levanta el mismo stack en tu PC sin el túnel, con Nginx publicado solo en `127.0.0.1:8020`. Así se valida este repositorio: los 71 tests de Playwright pasan contra Nginx, PHP-FPM, SSR, MySQL y Redis.

```bash
# .env.prodtest (local, nunca en git): las variables de la sección 3 con
# APP_URL=http://127.0.0.1:8020, SESSION_SECURE_COOKIE=false, SECURITY_HSTS=false,
# APP_ENV_FILE=.env.prodtest y TUNNEL_TOKEN=unused-locally.
C="docker compose -p blackpool-prodtest --env-file .env.prodtest -f compose.yaml -f compose.prodtest.yaml"
$C up -d --build --wait
$C exec app php artisan demo:reset --force   # datos limpios antes de cada pasada
E2E_BASE_URL=http://127.0.0.1:8020 bin/e2e
```

Problemas que detectó esta prueba y que ya están corregidos:

- **502 de Nginx:** las cabeceras de precarga (`Link`), la CSP y las cookies superaban el buffer FastCGI por defecto. Se ha aumentado `fastcgi_buffer_size`.
- **403 al servir vídeos:** Nginx (otro usuario) no podía leer los directorios del disco `media`, que se creaban con permisos 0700. Ahora se crean con permisos 0755/0644.
- **Cola:** `retry_after` (90 s) era menor que el timeout de ffmpeg (3600 s), lo que habría duplicado transcodificaciones largas.
- **Seeders:** no eran idempotentes y duplicaban lecciones al resembrar.

## 8. Monitorización y SEO

- **Laravel Pulse** en `/pulse` (solo administradores): peticiones lentas, jobs, excepciones y uso de caché.
- Logs: `docker compose logs -f app worker`.
- Disponibilidad: `curl -fsS https://blackpool.esteban-russo.com/up`.
- `https://blackpool.esteban-russo.com/sitemap.xml` lista las lecciones publicadas. Se puede enviar a Google Search Console y Bing Webmaster Tools, que son gratuitos.
- `robots.txt` solo permite indexar cuando `APP_ENV=production`.
- Si el contenedor `ssr` se cae, las páginas se renderizan en el navegador. Compruébalo con `docker compose ps ssr`.

## Si algo falla

| Síntoma                | Qué mirar                                                                                          |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| Error 1033             | El túnel no está conectado: `docker compose ps` y `docker compose logs tunnel`                     |
| Error 502              | El túnel llega pero Nginx no responde: la ruta debe ser `http://web:80`; `docker compose logs web` |
| URLs `http://` o bucle | Falta `X-Forwarded-Proto`: revisa que la ruta del túnel sea HTTP y `APP_URL` use `https://`        |
| Sin memoria            | `free -h`, `docker stats --no-stream`; baja los `mem_limit` de `compose.yaml`                      |
