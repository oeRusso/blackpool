# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses
[Conventional Commits](https://www.conventionalcommits.org/).

## [Unreleased]

### Changed

- No environment files in the repository, not even templates. `bin/make-env` generates the local, e2e and CI ones; the production `.env` lives only on the server.
- Backups stay on the VPS with rotation and a 1.5 GB cap, instead of an external bucket. Dropped `league/flysystem-aws-s3-v3`.
- The deployment guide only uses Cloudflare Free-plan features and a one-level subdomain.

## [2.1.0] — 2026-11-05

### Added

- English/Spanish interface:
    - language switcher in every layout and in Settings → Appearance;
    - detection from `Accept-Language`;
    - the choice is saved on the account and used for emails.
- Spanish translations for Laravel's validation, auth and password messages (`laravel-lang/common`, dev only).
- Inertia SSR for public pages, with a self-contained bundle and a new `ssr` container in production.
- SEO:
    - `SeoHead` (canonical, Open Graph, Twitter);
    - schema.org `LearningResource` JSON-LD;
    - `sitemap.xml`, dynamic `robots.txt` and a default social image.
- Tests: `LocalizationTest`, `SeoTest`, catalogue completeness checks (Pest and Vitest), and `i18n.spec.ts` / `seo.spec.ts` (no-JS rendering, hydration, metadata).

### Changed

- Dates are formatted in UTC with the interface locale, so server and client render the same text.
- Branded error pages share the Inertia props and use the visitor's language.
- Removed unused starter-kit layouts (header and split/card auth layouts).
- Updated Symfony components (security advisories).

## [2.0.0] — 2026-11-02

Complete rebuild of the legacy prototype (tag `legacy-baseline`). See `docs/ROADMAP.md`.

### Added

- Laravel 13 + Inertia 3 + Vue 3.5/TypeScript stack (starter kit), with passkeys and 2FA.
- Domain model: CEFR levels, categories, lessons (draft/published/scheduled, soft deletes), videos, quizzes, progress.
- Admin backoffice:
    - dashboard;
    - categories, lessons (cover to WebP, private PDFs), quiz editor;
    - users (role assignment) and the role/permission matrix.
- Hybrid video: chunked resumable uploads, queued ffmpeg HLS transcoding, YouTube/Vimeo oEmbed embeds and authorized streaming with X-Accel-Redirect.
- Student area:
    - catalogue with filters;
    - lesson page with player, worksheet, quiz, favourites and navigation;
    - "My learning" dashboard.
- Brand identity, landing page, branded error pages, dark mode and WCAG 2.1 AA fixes.
- Security headers with a nonce-based CSP; demo mode (protected accounts, one-click logins, nightly reset).
- Production Docker stack (Nginx, php-fpm, worker, scheduler, MySQL, Redis), Backblaze B2 backups, Laravel Pulse and GitHub Actions CI/CD.
- Tests: Pest feature and architecture (96.9% coverage), Vitest unit tests, Playwright E2E with axe-core.

### Fixed (from the legacy app)

- Permissions were only enforced in the UI; any registered user could modify categories.
- Lesson and role modules were empty stubs; the lesson form did not send level, categories or files.
- Deleting a level cascaded to all its lessons; the pivot table had no foreign keys.
- Tests could run against the real database; credentials were published in the README.
