# BlackPool

Plataforma para aprender inglés organizada por niveles CEFR (A1–C2), con un backoffice para gestionar categorías y lecciones.

> 🚧 En plena refactorización. La hoja de ruta está en [`docs/ROADMAP.md`](docs/ROADMAP.md) y la versión original se conserva en el tag `legacy-baseline`.

## Stack actual

Laravel 11 · Jetstream (Inertia + Vue 3) · Tailwind CSS · spatie/laravel-permission

## Puesta en marcha (desarrollo)

```bash
cp .env.example .env
composer install && npm install
php artisan key:generate
php artisan migrate --seed   # niveles A1–C2, roles admin/editor y usuarios de demo
composer dev
```

Los usuarios de demo se definen en `database/seeders/UserSeeder.php` y son **solo para desarrollo local**.
