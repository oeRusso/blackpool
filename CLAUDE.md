# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

BlackPool is an English-learning platform (CEFR levels A1–C2) built as a portfolio project. It is being refactored according to the phased plan in `docs/ROADMAP.md`. **Read it before making structural changes.** The current code is the _legacy baseline_ (git tag `legacy-baseline`).

Constraint: only free / open-source tools and services. Target deployment is a Contabo VPS behind Cloudflare (free plan, so request bodies are capped at 100 MB and large uploads must be chunked).

## Stack (current → target)

| Layer    | Current (legacy)                                             | Target (Phase 1)                            |
| -------- | ------------------------------------------------------------ | ------------------------------------------- |
| Backend  | Laravel 11, Jetstream 5 (Fortify), Sanctum                   | Laravel 13 + Vue starter kit (Fortify)      |
| Frontend | Inertia 1, Vue 3.5 (JS, Options+`<script setup>` mix), Ziggy | Inertia 3, Vue 3.5 + TypeScript, shadcn-vue |
| CSS      | Tailwind 3                                                   | Tailwind 4                                  |
| AuthZ    | spatie/laravel-permission 6                                  | same + Policies                             |
| Tests    | PHPUnit 11 (Jetstream defaults only)                         | Pest 4, Larastan, Vitest, Playwright        |

## Commands

PHP is **not** installed on the host; run PHP tooling through Docker (Laravel Sail once added, or `docker run --rm -v "$PWD":/app -w /app composer:2 ...`). Node 24 and ffmpeg are available on the host.

```bash
npm run dev            # Vite dev server
npm run build          # production assets → public/build
composer dev           # serve + queue:listen + pail + vite (inside PHP env)
php artisan test       # all tests
php artisan test --filter=SomeTest   # single test
php artisan migrate:fresh --seed     # seeds levels A1–C2, roles and demo users
./vendor/bin/pint      # code style
```

## Architecture & domain

- **Models** (`app/Models`): `Category` ⇄ `Lesson` (many-to-many via `category_lesson`), `Lesson` → `Level` (belongs to, FK `level_id`), `User` (uses `HasRoles`).
- **Roles/permissions** (`database/seeders/RoleSeeder.php`): `admin` = CRUD on roles, lessons and categories; `editor` = CRUD on lessons and categories. Permission names follow `"<verb> <resource>"`, e.g. `create lessons`.
- Permissions reach the frontend through `HandleInertiaRequests::share()` as `user.roles` / `user.permissions`. Vue pages use them only to hide UI.
- Routes (`routes/web.php`): `/` is public. `dashboard` and the resource routes `categories`, `lessons` and `roles` sit behind `auth:sanctum` + `verified`.
- Inertia page names may use dots (`'Lessons.Index'`). `resources/js/app.js` converts them to paths.

## Known issues in the legacy baseline (fix, don't copy)

- **Authorization is not enforced on the server.** There are no policies or `can` middleware, and `CategoryRequest::authorize()` returns `true`. Any registered user can call the CRUD endpoints directly. Always add Policies or FormRequest authorization for new endpoints.
- `LessonController` (except `index`/`create`) and `RoleController` are empty stubs. `Lessons/Edit.vue` is a copy of the category page.
- `Lesson::levels()` is wrongly `belongsToMany`; the schema is `belongsTo`.
- `category_lesson` has no FKs or unique constraint.
- The `level_id` FK cascades deletes to lessons.
- `phpunit.xml` does not force SQLite, so tests would run against the `.env` database.
- `resources/views/app.blade.php` links a hashed CSS file, and `vite.config.js` hard-codes `base: '/projects/blackpool/'`.

## Conventions

- Thin controllers. Business logic goes in `app/Actions/*`, validation and authorization in FormRequests, access rules in Policies.
- Use PHP enums for statuses, roles and permissions.
- Every business rule gets a feature test, including authorization tests per role.
- Conventional Commits; one branch/PR per roadmap phase.
- Never commit `.env`, and never publish real credentials in the README.
