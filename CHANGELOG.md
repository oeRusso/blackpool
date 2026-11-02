# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/) and the project uses
[Conventional Commits](https://www.conventionalcommits.org/).

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
