# BlackPool: plan de refactorización, reforma y crecimiento

## Contexto

BlackPool es una plataforma para aprender inglés. Hoy usa Laravel 11, Jetstream (Inertia 1), Vue 3 en JS, Tailwind 3 y spatie/laravel-permission. El objetivo es convertirla en un **proyecto de portfolio profesional** con:

- stack moderno,
- reglas de negocio aplicadas en el backend,
- área de alumno,
- **subida de videos**,
- tests, CI y despliegue en **VPS Contabo con dominio en Cloudflare**.

Restricción: solo herramientas gratuitas u open source.

Decisiones tomadas:

- Migrar a Laravel 13, Inertia 3 y el Vue starter kit (TypeScript, shadcn-vue, Tailwind 4).
- Videos con enfoque **híbrido**: subida propia con transcodificación o embed de YouTube/Vimeo.
- Alcance de **plataforma completa** (admin y alumno).

Las buenas prácticas se consultaron en la API pública de Context7 (`/laravel/docs` rama 13.x e `/inertiajs/inertia` rama 3.x). Context7 no está instalado como MCP en esta sesión.

> **Estado (2026-11-02): fases 0–10 completadas.** Detalle en la sección «Estado de ejecución» al final del documento.

---

## 1. Reglas de negocio actuales: verificación

| #   | Regla (según el código)                                                                                                                            | Dónde                                                     | Estado                                                                                                                     |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| R1  | Roles: `admin` (12 permisos: CRUD de roles, lecciones y categorías) y `editor` (CRUD de lecciones y categorías, sin roles)                         | `database/seeders/RoleSeeder.php`                         | ✅ en el seed                                                                                                              |
| R2  | Los permisos solo **ocultan botones** en Vue (`$page.props.user.permissions.includes(...)`)                                                        | `Pages/*/Index.vue`, `Layouts/AppLayout.vue`              | ❌ **Sin control en el backend**: ni policies ni middleware `can`, y `CategoryRequest::authorize()` devuelve `true`        |
| R3  | El registro público está abierto y el usuario nuevo **no recibe rol**                                                                              | `config/fortify.php`, `Actions/Fortify/CreateNewUser.php` | ❌ Combinado con R2, **cualquier usuario registrado puede crear, editar o borrar categorías** con peticiones HTTP directas |
| R4  | Categoría: `name` obligatorio, string, máx. 100, único (mensaje propio)                                                                            | `Http/Requests/CategoryRequest.php`                       | ⚠️ Solo se valida en la app. No hay índice `unique` en la base de datos (riesgo de carrera)                                |
| R5  | Borrar una categoría no comprueba si tiene lecciones                                                                                               | `CategoryController::destroy`                             | ⚠️ Deja filas huérfanas en el pivote (no hay FK)                                                                           |
| R6  | Niveles CEFR fijos A1–C2                                                                                                                           | `LevelSeeder.php`, `levels.name` string(2)                | ✅ (sin CRUD, correcto)                                                                                                    |
| R7  | Lección: nombre (150), descripción, imagen opcional, `content_uri` y `pdf_uri` obligatorios, un nivel, N categorías, `is_free` (false por defecto) | migración `create_lessons_table`                          | ⚠️ Solo en el esquema. No hay validación ni store/update/destroy                                                           |
| R8  | Borrar un nivel **borra en cascada todas sus lecciones**                                                                                           | FK `level_id ... onDelete('cascade')`                     | ❌ Peligroso; debería ser `restrict`                                                                                       |
| R9  | CRUD de lecciones                                                                                                                                  | `LessonController.php`                                    | 🚧 Solo existen `index` y `create`. El resto está vacío                                                                    |
| R10 | CRUD de roles                                                                                                                                      | `RoleController.php`                                      | 🚧 Vacío y sin páginas Vue. El enlace "Roles" del menú lleva a una respuesta vacía                                         |
| R11 | Contraseñas de 8+ caracteres con 2FA disponible. La verificación de email está desactivada                                                         | `config/fortify.php`                                      | ✅ / ⚠️                                                                                                                    |
| R12 | Paginación de 25 elementos                                                                                                                         | Constantes en los controladores                           | ✅                                                                                                                         |

### Bugs y deuda detectados

**Modelos y migraciones**

- `Lesson::levels()` es `belongsToMany` con un esquema `belongsTo`. Debería ser `level()`.
- El pivote `category_lesson` usa `unsignedInteger` (los IDs son bigint), no tiene FKs ni `unique(category_id, lesson_id)` y tiene un `id`/timestamps innecesarios.
- `text('description')->max(255)` no hace nada.
- `$guarded = []` en todos los modelos, sin factories de dominio.

**Formulario de lecciones**

- `Lessons/Form.vue`: el `<select>` de nivel no tiene `v-model`.
- Las categorías elegidas no llegan al `form`.
- El botón "Upload PDF" no hace nada.
- `Lessons/Create.vue` solo inicializa `name`.
- `Lessons/Edit.vue` es una copia de Categories/Edit.
- `CollectionSelector.vue`: el botón "Add" no tiene `type="button"`, así que **envía el formulario**. `currentSelection = ref(1)` añade un número si no se ha elegido nada.

**Vistas**

- Borrar usa `<Link @click>` sin `href`; debería ser `<button>` con un diálogo de confirmación.
- `Categories/Create.vue` muestra el título "Edit Category".
- `Categories/Edit.vue` mete el formulario dentro del slot `#header`.
- Clases con erratas: `bg:white`, `max-ww-7xl`, `sm:px:6`.

**Infraestructura**

- `resources/views/app.blade.php` enlaza un CSS compilado con hash fijo (`../../public/build/assets/app-ESmCDE0_.css`).
- `vite.config.js` tiene `base: '/projects/blackpool/'` fijo.
- `Welcome.vue` arma las URLs de imágenes con variables de entorno a mano.

**Seguridad y configuración**

- El `.env` tiene `APP_ENV=production` con `APP_DEBUG=true` y credenciales reales.
- Las credenciales `admin/admin` están publicadas en el README.
- `composer.phar` y `composer-setup.php` están en la raíz.
- **No es un repositorio git.**

**Tests y otros**

- `phpunit.xml` tiene SQLite comentado, así que los tests con `RefreshDatabase` **usarían la base de datos del `.env`**.
- Solo hay tests por defecto de Jetstream; ninguno de dominio.
- `HandleInertiaRequests` usa `getPermissionsViaRoles()` (ignora los permisos directos) y comparte strings en vez de un mapa `can`.
- `DashboardController::index` pasa versiones de Laravel y PHP al frontend (exposición innecesaria).

---

## 2. Qué le falta para ser "profesional"

- Autorización real en el servidor.
- Dominio completo: lecciones, medios, videos y progreso.
- Área de alumno.
- Tests de reglas de negocio.
- Análisis estático y CI.
- README y documentación de arquitectura.
- Despliegue reproducible, observabilidad y backups.
- Demo pública con datos semilla.
- UI coherente (sistema de diseño, modo oscuro, i18n ES/EN).

---

## 3. Plan por fases

### Fase 0: Fundaciones (1–2 días)

1. `git init`.
2. Ajustar el `.gitignore` y eliminar `composer.phar` y `composer-setup.php`.
3. Revisar que `.env` nunca se suba y **rotar la contraseña de la base de datos del hosting**.
4. Generar `CLAUDE.md` (`/init`) con los comandos, la arquitectura y las convenciones.
5. Herramientas de calidad: **Laravel Pint**, **Larastan** (nivel 6→8), **Pest 4** (con `pest-plugin-arch`), ESLint con Prettier (incluidos en el starter kit) y **Vitest**.
6. Activar SQLite en memoria en `phpunit.xml`.
7. GitHub (repositorio público) y **GitHub Actions**: lint, análisis estático, tests y build en cada PR.
8. Conventional Commits y una rama de trabajo por fase.

### Fase 1: Migración al stack moderno

Base: proyecto nuevo con el **Laravel 13 Vue starter kit** (Fortify, Inertia 3, Vue 3.5 + TS, shadcn-vue, Tailwind 4), al que se **porta el dominio**. Hay poco código propio, así que es más limpio que actualizar Jetstream en el sitio.

- Portar modelos, migraciones (corregidas en la Fase 2), seeders, `spatie/laravel-permission` y las páginas.
- Ziggy pasa a las rutas tipadas que traiga el kit (Wayfinder) o se mantiene Ziggy si no las trae.
- Se elimina lo que no se usa de Jetstream (teams, API tokens).
- `AppServiceProvider::boot()`: `Model::shouldBeStrict(! app()->isProduction())`, `Model::automaticallyEagerLoadRelationships()` si está disponible y `DB::prohibitDestructiveCommands()` en producción.
- Arreglar el blade raíz y Vite: sin CSS fijo y con `base` derivado de `ASSET_URL`.

### Fase 2: Dominio y datos

Las migraciones nuevas sustituyen a las actuales, porque el proyecto aún no tiene datos reales.

**`lessons`**

- Campos: `slug` único, `title`, `summary` (string 255), `body` (texto enriquecido opcional), `level_id`, `author_id`, `status`, `published_at`, `position`, `duration_seconds`, `is_free`, `softDeletes`.
- FK de nivel con `restrictOnDelete`.

**`category_lesson`**

- `foreignId` en ambas columnas, `cascadeOnDelete` y clave primaria compuesta.

**`categories`**

- `slug` y `name` únicos, ambos con índice en la base de datos.

**`levels`**

- Añadir `code` (A1…C2), `order` y `description`.

**`media`**

- Usar **spatie/laravel-medialibrary** (gratuito) para la portada (conversión a WebP) y el PDF.

**`videos`**

- Campos: `lesson_id`, `source` (enum `upload|youtube|vimeo`), `status` (`pending|uploading|processing|ready|failed`), `external_id`, `disk`, `original_path`, `hls_path`, `mp4_path`, `thumbnail_path`, `duration_seconds`, `size_bytes`, `error`.

**Progreso y quizzes**

- `lesson_user` (progreso): `completed_at`, `last_position_seconds`, `favorited_at`.
- `quizzes`, `questions`, `options` y `quiz_attempts`, con un modelo simple de opción múltiple.

**Código**

- Enums PHP en `app/Enums`: `LessonStatus`, `VideoSource`, `VideoStatus`, `Permission` y `Role`.
- Relaciones corregidas: `Lesson::level()` es `belongsTo`.
- `$fillable` explícito y factories para todos los modelos.
- Seeders de demo realistas (≈30 lecciones repartidas por niveles).

### Fase 3: Autorización real (crítica)

- **Policies** (`CategoryPolicy`, `LessonPolicy`, `VideoPolicy`, `RolePolicy` y `UserPolicy`), registradas con `#[UsePolicy]` o por autodescubrimiento.
- Un `Gate::before` para el super-admin.
- Rutas admin agrupadas bajo el prefijo `/admin`, con middleware `can:` o `#[Authorize]` en los controladores.
- `FormRequest::authorize()` delega en las policies.
- Roles: `admin`, `editor` y `student`. Con la plataforma completa añadimos `premium`, que asigna el admin (no hay pasarela de pago para mantener coste 0).
- `CreateNewUser` asigna `student`.
- `LessonPolicy::view`: si la lección es gratuita y está publicada, cualquiera puede verla; si no es gratuita, hace falta `premium`, `editor` o `admin`.
- `HandleInertiaRequests` comparte un mapa `auth.can` calculado en el backend (por ejemplo `{ lessons: { create: true } }`) en lugar de arrays de strings.
- Rate limiting en login, subidas y quizzes.
- Matriz de tests Pest rol × acción, que cubre R2 y R3.

### Fase 4: Backoffice completo

**Arquitectura**

- Controladores finos.
- **Actions** (`app/Actions/Lessons/CreateLesson.php`, `UpdateLesson`, `PublishLesson`, etc.).
- FormRequests con `File::types(['pdf'])->max(...)` y `File::image()->max(...)`.
- API Resources o DTOs para las props de Inertia.

**CRUD**

- **Categorías**: búsqueda, ordenación y borrado con confirmación. Si tiene lecciones, se impide el borrado o se pide confirmación para desvincularlas.
- **Lecciones**: editor completo con nivel, categorías (multiselect de shadcn), portada, PDF, video, estado borrador/publicado, orden y vista previa.
- **Roles, permisos y usuarios**: listados y asignación de roles.

**UX del panel**

- Tablas con TanStack Table (shadcn DataTable), paginación de servidor y filtros con query string.
- Toasts (vue-sonner) a partir de mensajes flash y `AlertDialog` para borrar.
- Dashboard con KPIs: lecciones por nivel, alumnos, completadas y videos en proceso.

### Fase 5: Videos (híbrido)

**Diseño**

- Contrato `App\Contracts\VideoSource` con drivers `UploadedVideo` y `EmbeddedVideo` (YouTube/Vimeo).
- `VideoManager` resuelve qué reproductor y qué URL entregar.

**Embed**

- Pegar la URL, parsear el ID y validarlo con **oEmbed** (gratis, sin API key).
- Renderizar con un componente ligero (lite-embed) que carga el iframe al hacer clic.

**Subida propia**

1. **Subida resumible por chunks**. Es necesaria porque el plan gratuito de Cloudflare limita el cuerpo de la petición a 100 MB.
    - Cliente: **Uppy** (MIT) con `@uppy/tus` o XHR por chunks (≤ 50 MB).
    - Servidor: endpoint tus (`ankitpokhrel/tus-php`) o un endpoint de chunks propio. Antes de elegir hay que comprobar que la librería sigue mantenida.
    - Límites: tipos `mp4/mov/webm` y tamaño máximo configurable.
2. Al completarse: el estado pasa a `processing` y se despacha `ProcessUploadedVideo` en la cola `media`.
    - Usa **pbmedia/laravel-ffmpeg**.
    - `ffprobe` saca la duración y la miniatura.
    - Genera **HLS** en 360p y 720p, más un MP4 de respaldo.
    - Configuración del job: `$timeout` alto, `$tries = 2`, `failed()` marca `failed` y guarda el error, y `ShouldBeUnique` por video.
    - Encadenar con `Bus::chain`: miniatura → HLS → limpieza del original.
3. **Estado en tiempo real** con **Laravel Reverb** (WebSockets autoalojados, gratis) o `usePoll` de Inertia como alternativa.
4. **Reproducción**:
    - Reproductor **Vidstack** o video.js con hls.js (gratis).
    - Lecciones premium: segmentos HLS servidos con **URLs firmadas** (`URL::temporarySignedRoute`) más `X-Accel-Redirect` de Nginx, para que PHP no transmita los bytes.
    - Se guarda `last_position_seconds` para retomar el video donde se dejó.

**Almacenamiento**

- Disco `media` local en el VPS de Contabo, que tiene disco amplio.
- Opción de pasar a **Backblaze B2** (10 GB gratis, compatible con S3, sin coste de salida a través de Cloudflare) cambiando solo el disco.

**Cloudflare**

- Servir los medios desde el subdominio `media.` y revisar los ToS de Cloudflare sobre video en el plan gratuito.
- Por prudencia, `media.` en "DNS only" o servido desde B2.

**Tests**

- `Storage::fake`, `Queue::fake` y `Bus::assertChained`, más un test de integración de ffmpeg marcado como `@group ffmpeg`.

### Fase 6: Área de alumno

**Páginas públicas**

- Landing.
- Catálogo filtrable por nivel CEFR, categoría, gratis/premium y búsqueda.
- Búsqueda con **Laravel Scout** (driver `database`), con Meilisearch autoalojado como mejora opcional.
- Página de lección: reproductor, PDF descargable (URL temporal), quiz, "marcar como completada", favoritos y lección anterior/siguiente.

**"Mi aprendizaje"**

- Progreso por nivel, lecciones en curso y racha.
- Usar deferred props e infinite scroll de Inertia 3 donde aporten.

**SEO**

- Inertia SSR.
- Meta y Open Graph por lección.
- `spatie/laravel-sitemap`.

**Cuenta y comunicación**

- **Verificación de email activada**.
- SMTP gratuito: Brevo (300/día) o Resend (3000/mes); Mailpit en desarrollo.
- i18n ES/EN con `laravel-vue-i18n`, que encaja con una app para aprender inglés.

### Fase 7: Mejoras visuales y UX

**Sistema de diseño**

- shadcn-vue con Tailwind 4 y tokens propios: paleta de marca, tipografía y logo.
- Modo oscuro (incluido en el kit) e iconos Lucide.

**Contenido de las páginas**

- Landing con hero, niveles A1–C2, lecciones destacadas y llamadas a la acción.
- Skeletons, estados vacíos, mensajes de error amables y páginas 403/404/500 propias con Inertia.
- Imágenes en WebP con `loading=lazy`.

**Calidad visual**

- Accesibilidad: foco visible, labels y contraste, auditados con axe y Lighthouse (gratis).
- Responsive verificado en móvil.

### Fase 8: Calidad y testing

- **Pest**: tests de feature por regla de negocio (R1–R12 y las nuevas), de validación y de policies.
- Arch tests: por ejemplo, controladores sin acceso directo a `DB` y modelos `final` donde aplique.
- Cobertura ≥ 80 % en el dominio.
- **Larastan** a nivel 8.
- Frontend: **Vitest** con Vue Test Utils para los componentes clave (formulario de lección, selector de categorías, uploader).
- E2E con **Playwright** (gratis) para los flujos críticos: registro → ver lección gratis → bloqueo de premium; y editor crea lección + sube video → listo.
- Todo se ejecuta en GitHub Actions; la base de datos de CI es MySQL o Postgres en un contenedor de servicio.

### Fase 9: DevOps y despliegue (Contabo + Cloudflare)

**Entornos**

- Desarrollo: **Laravel Sail** (Docker) con MySQL u Postgres, Redis, Mailpit y ffmpeg.
- Producción en Contabo (Ubuntu LTS):
    - Opción A, Docker Compose: nginx, php-fpm 8.4, base de datos, Redis, `queue:work --queue=media,default` vía Supervisor o **Horizon** (gratis), Reverb y scheduler.
    - Opción B: los mismos servicios nativos.

**CD**

- GitHub Actions → SSH → **Deployer** (gratis) con zero-downtime: migraciones, `optimize` y `queue:restart`.

**Cloudflare**

- DNS y proxy.
- SSL **Full (strict)** con Origin Certificate.
- `trustProxies` con las IPs de Cloudflare.
- Reglas WAF y rate limit gratuitas.
- Caché de `/build/*`.

**Seguridad del servidor**

- ufw, fail2ban y SSH solo con clave.
- Cabeceras de seguridad y CSP.
- `APP_DEBUG=false`.

**Backups y observabilidad**

- Backups con **spatie/laravel-backup** a Backblaze B2 (10 GB gratis), programados.
- **Laravel Pulse** (métricas).
- Sentry Developer (gratis) o GlitchTip autoalojado.
- **Uptime Kuma** o UptimeRobot (gratis).

### Fase 10: Presentación para el portfolio

**README profesional**

- Descripción, capturas y GIF de los flujos.
- Stack y diagrama de arquitectura (Mermaid): flujo de subida → cola → ffmpeg → HLS → reproductor.
- Decisiones técnicas (carpeta `docs/adr/`), cómo ejecutarlo y badges de CI.

**Demo pública**

- Cuentas `admin`, `editor`, `student` y `premium` con contraseñas de demo.
- **Modo demo**: un middleware bloquea las acciones destructivas en las cuentas de demo y el scheduler reinicia los datos cada noche (`migrate:fresh --seed` en la base de datos de demo).

**Extras**

- CHANGELOG, LICENSE (MIT) y un breve caso de estudio (problema → solución → resultados).

---

## 4. Orden recomendado y entregables

- **0 → 1 → 2 → 3**: cimientos. Desbloquean todo lo demás; la Fase 3 corrige el fallo de seguridad.
- **4 → 5**: backoffice completo con videos. Es lo más vistoso técnicamente.
- **6 → 7**: producto de cara al alumno y diseño.
- **8**: transversal. Cada fase se cierra con sus tests.
- **9 → 10**: despliegue y presentación.

Cada fase es una PR con su CI en verde.

## 5. Archivos críticos

**Se reescriben o corrigen**

- `routes/web.php`
- `app/Http/Controllers/{Category,Lesson,Role}Controller.php`
- `app/Http/Requests/CategoryRequest.php`
- `app/Models/{Lesson,Category,Level}.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `database/migrations/2024_12_*`
- `database/seeders/*`
- `resources/js/Components/Lessons/Form.vue`
- `resources/js/Components/Common/CollectionSelector.vue`
- `resources/js/Pages/{Categories,Lessons}/*`
- `resources/views/app.blade.php`
- `vite.config.js`
- `phpunit.xml`

**Se crean**

- `app/Policies/*`, `app/Actions/{Lessons,Videos}/*`, `app/Enums/*`
- `app/Contracts/VideoSource.php`, `app/Jobs/ProcessUploadedVideo.php`
- `tests/Feature/{Authorization,Lessons,Videos,Progress}/*`
- `.github/workflows/ci.yml`, `docker-compose`/Sail, `deploy.php`, `CLAUDE.md`, `README.md`

**Se reutiliza**

- La lógica de seeders de roles y niveles (`RoleSeeder`, `LevelSeeder`), adaptada a enums.
- `CategoryRequest` como patrón de FormRequest.
- Los componentes de formulario de shadcn-vue que trae el kit, en lugar de los de Jetstream.

## 6. Verificación

- `./vendor/bin/pest --coverage`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint --test`, `npm run lint`, `npm run test` (Vitest) y `npm run build` en verde, en local y en CI.
- Test de regresión de seguridad: un usuario `student` recibe **403** en `POST /admin/categories` y `DELETE /admin/lessons/{id}`.
- Flujo manual con `composer dev` (y `php artisan reverb:start`):
    1. El editor crea una lección con PDF y video subido.
    2. El estado pasa de processing a ready en tiempo real.
    3. El alumno reproduce la lección en HLS y la marca como completada.
    4. Una lección premium queda bloqueada para el alumno sin rol `premium`.
    5. Un embed de YouTube se valida y se reproduce.
- Playwright E2E sobre el entorno Sail.
- Tras el deploy: Lighthouse ≥ 90, SSL Full (strict) comprobado y backup de prueba restaurado.

---

## Estado de ejecución (2026-11-02)

| Fase                | Estado | Notas y desviaciones respecto al plan                                                                                                                                                                            |
| ------------------- | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0 · Fundaciones     | ✅     | git, `CLAUDE.md`, Pint, Larastan 7, Pest 5, Vitest, CI. Sin PHP en el host: toolchain en Docker (`bin/d`).                                                                                                       |
| 1 · Stack moderno   | ✅     | Starter kit Laravel 13 + Inertia 3 + Vue/TS + Wayfinder; passkeys incluidas.                                                                                                                                     |
| 2 · Dominio y datos | ✅     | Portada y PDF guardados como rutas propias con conversión a WebP por GD, **sin spatie/medialibrary** (menos dependencias). Seeders idempotentes.                                                                 |
| 3 · Autorización    | ✅     | Policies + `can:access-admin`. **Sin `Gate::before`**, para que las reglas de negocio también apliquen a los admins.                                                                                             |
| 4 · Backoffice      | ✅     | Tablas con componentes shadcn (no TanStack); filtros por query string; editor de quiz.                                                                                                                           |
| 5 · Vídeos          | ✅     | Protocolo de chunks propio en lugar de tus-php/Uppy; ffmpeg vía `Process` en lugar de pbmedia/laravel-ffmpeg; estado por **polling** (`usePoll`) en lugar de Reverb.                                             |
| 6 · Área de alumno  | ✅     | Catálogo, lección, quiz, progreso y favoritos. Búsqueda con LIKE (sin Scout). **SSR** de las páginas públicas, canonical, Open Graph, JSON-LD, `sitemap.xml` propio (sin spatie/laravel-sitemap) y `robots.txt`. |
| 7 · UI/UX           | ✅     | Marca, landing, páginas de error, modo oscuro, axe WCAG 2.1 AA en 12 páginas. **i18n ES/EN** con un `useI18n()` propio en lugar de laravel-vue-i18n (ADR 0005).                                                  |
| 8 · Calidad         | ✅     | 144 tests de Pest (96,9 % de cobertura, mínimo 90 % en CI), tests de arquitectura, 17 de Vitest, 62 de Playwright.                                                                                               |
| 9 · DevOps          | ✅     | Docker multi-stage, Nginx con X-Accel-Redirect, CSP con nonce, backups a B2, Pulse y deploy por SSH. Stack de producción validado en local con Playwright. Sin Deployer ni Horizon (no hacían falta).            |
| 10 · Portfolio      | ✅     | README, ADRs, CHANGELOG, LICENSE, capturas automáticas, modo demo con reset nocturno.                                                                                                                            |

**Actualización 2026-11-05: i18n y SEO.** Interfaz en inglés y español, SSR, sitemap y datos estructurados (ADR 0005). Tests: 162 de Pest, 25 de Vitest y 71 de Playwright.

### Próximos pasos sugeridos

1. Desplegar en el VPS siguiendo `docs/DEPLOYMENT.md` y añadir la URL de la demo al README.
2. Notificaciones en tiempo real con Laravel Reverb (sustituirían el polling).
3. Mover el disco `media` a Backblaze B2 si el volumen de vídeo crece.
4. URLs por idioma (`/es/...`) con `hreflang` si la interfaz en español tiene que posicionar por separado.
