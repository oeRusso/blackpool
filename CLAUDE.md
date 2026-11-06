# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

BlackPool is an English-learning platform built as a portfolio project:

- lessons by CEFR level (A1–C2) with HLS video, PDF worksheets, quizzes and progress tracking;
- an admin backoffice.

`README.md` is the overview, `docs/adr/` explains the key decisions and `docs/DEPLOYMENT.md` covers production. The pre-refactor prototype lives under the git tag `legacy-baseline`.

**Constraint:** only free or open-source tools and services. Production is a Contabo VPS behind Cloudflare's free plan, which caps request bodies at 100 MB, so large uploads must be chunked.

## Commands

PHP is **not** installed on the host. Run PHP and Node tooling through the dev image with `bin/d`. Build the image once with `docker build -t blackpool-dev docker/dev`. The dev SQLite database lives in the `blackpool-data` Docker volume (`/data/database.sqlite`).

```bash
bin/d php artisan test                         # Pest (Feature/Domain, Arch, kit tests)
bin/d php artisan test --filter=VideoTest      # single file / test name
bin/d php artisan test --coverage --min=90     # CI gate
bin/d ./vendor/bin/phpstan analyse --memory-limit=1G   # Larastan level 7
bin/d ./vendor/bin/pint                        # PHP code style
bin/d npm run check:fix                        # Vite+ format + lint (run after editing .vue/.ts)
bin/d npm run types:check                      # vue-tsc
bin/d npm run test                             # Vitest (*.test.ts next to the code)
bin/d npm run build                            # also regenerates Wayfinder routes
bin/d php artisan wayfinder:generate --with-form   # after adding/changing routes
bin/make-env                                   # local .env (gitignored) with a generated APP_KEY
bin/d php artisan migrate:fresh --seed         # demo data (idempotent seeders)
bin/e2e                                        # Playwright: seeds an e2e DB, starts server + queue worker
bin/e2e e2e/video.spec.ts --grep upload        # subset
E2E_SKIP_BUILD=1 bin/e2e                       # skip the asset build
E2E_BASE_URL=http://127.0.0.1:8020 bin/e2e     # against an already running stack
```

Playwright runs in the official `mcr.microsoft.com/playwright` image. Use the same version as `@playwright/test`. Test ids use the `data-test` attribute.

## Architecture

- **Roles and permissions:** `app/Enums/RoleName.php` and `PermissionName.php` define them, `RolesAndPermissionsSeeder` seeds them (spatie/laravel-permission).
    - Roles are `admin`, `editor`, `premium` and `student`. Self-registered users get `student`.
- **Authorization is always server-side.**
    - The `/admin` prefix uses `can:access-admin` (`routes/admin.php`). Every action then authorizes through a Policy (`app/Policies`) or `FormRequest::authorize()`.
    - `LessonPolicy::view` covers lesson metadata (published, or staff). `LessonPolicy::watch` covers the video, PDF, quiz and progress (free lessons need an account, premium lessons need `view premium lessons`).
    - `HandleInertiaRequests` shares `auth.can`, a server-computed ability map that the UI uses only to show or hide controls.
- **Domain logic:** controllers stay thin and logic lives in `app/Actions` (`SaveLesson`, `GradeQuiz`, `UpdateLessonProgress`) and `app/Services/Video`.
- **Video:** browser → `ChunkedUploadManager` (raw `PUT` chunks) → `ProcessUploadedVideo` job on the `media` queue (`HlsTranscoder`: ffprobe, thumbnail, HLS 360p/720p) → `VideoStreamController`, which authorizes every segment and uses `X-Accel-Redirect` when `MEDIA_ACCEL_REDIRECT_PREFIX` is set. Embeds go through `EmbedResolver` (oEmbed).
- **Frontend:** Inertia pages live in `resources/js/pages` (`learn/*`, `admin/*`, `auth/*`, `settings/*`). The layout is chosen in `resources/js/app.ts`: `learn/`, `Welcome` and `Error` use `PublicLayout`. Routes are typed with Wayfinder: `@/routes/...` and `@/actions/...`. The generated files are gitignored.
- **i18n and SSR:**
    - UI strings are English keys translated with `useI18n().t()` (`resources/js/lib/i18n.ts`) on the frontend and `__()` on the backend.
    - Spanish lives in `lang/es.json`. Tests fail if a key is missing, so add the translation with every new string.
    - `SetLocale` resolves the language from the account, then the cookie, then `Accept-Language`.
    - Public pages are server-rendered by the Node SSR bundle (`bootstrap/ssr/app.js`, built by `npm run build`). Code that runs during render must not touch `window` or `document`, and must not depend on the timezone.
- **Security:** `SecurityHeaders` sets a nonce-based CSP; inline scripts need `nonce="{{ Vite::cspNonce() }}"`, and new third-party origins must be added to the policy. With `DEMO_MODE=true`, `ProtectDemoAccounts` stops the shared demo accounts from changing their credentials, and `demo:reset` runs nightly.

## Conventions

- Every business rule gets a Pest test in `tests/Feature/Domain`, including authorization per role. Use the helper `userWithRole(RoleName::X)` from `tests/Pest.php`. User-visible flows also get a Playwright spec.
- `tests/Arch/ArchitectureTest.php` enforces the architecture:
    - no debug calls;
    - `env()` only in `config/`;
    - string-backed enums;
    - queued jobs;
    - suffixed controllers, requests and policies.
- Models use the `#[Fillable]` attribute and `CarbonInterface` in docblocks (dates are `CarbonImmutable`). `Model::shouldBeStrict()` is on outside production.
- Seeders must be idempotent and must not use factories, because production images have no Faker.
- Keep `queue.*.retry_after` above `ProcessUploadedVideo::$timeout`; a test guards this.
- Use Conventional Commits, and keep CI green: Pint, Larastan, `vp check`, vue-tsc, Vitest, Pest with ≥ 90% coverage, and Playwright.
- Never commit `.env*` files, not even templates. Local ones are generated by `bin/make-env`; the production `.env` is written by hand on the server (`docs/DEPLOYMENT.md`).
- Deployments must cost nothing: only free/open-source tools and free-plan features of Contabo and Cloudflare. Backups stay on the VPS.
