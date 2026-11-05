# ADR 0005 — Lightweight i18n and Inertia SSR for SEO

- **Status:** accepted · 2026-11-05

## Context

- The audience is Spanish speakers learning English, so the interface has to be available in Spanish. The lessons stay in English, because that is what students are learning.
- Public pages (landing page, catalogue, lessons) should be indexable and should show content before JavaScript loads. A client-only Inertia app serves an empty `<div id="app">`.
- Everything must be free or open source and run on the same VPS.

## Decision

**Translations**

- English source strings are the keys, as in Laravel's `__()`. `lang/es.json` is the single catalogue shared by the backend (flash messages, validation, policies, emails) and the frontend.
- Laravel's own Spanish strings (validation, auth, passwords) come from `laravel-lang/common`, a dev-only dependency.
- The frontend uses a small `useI18n()` composable (`resources/js/lib/i18n.ts`) instead of `laravel-vue-i18n` or `vue-i18n`.
    - It reads the locale from the Inertia page props on every call, so concurrent SSR renders cannot leak a locale through global state.
    - It needs no async loading, so SSR stays synchronous.
- `SetLocale` resolves the locale in this order:
    1. the signed-in user's saved choice (`users.locale`);
    2. the `locale` cookie set by the switcher;
    3. `Accept-Language`;
    4. the app default.
- `User` implements `HasLocalePreference`, so notifications are sent in the user's language.
- Missing translations fall back to readable English.
    - A Pest test fails if a server-side `__()` key has no Spanish translation.
    - A Vitest test does the same for every `t('…')` call in the frontend.

**Server-side rendering**

- Inertia 3's Vite plugin reuses `app.ts` for the SSR build. `ssr.noExternal` makes the bundle self-contained, so the production `ssr` container is a plain `node:24-alpine` image with no `node_modules`.
- The signed-in areas (`admin/*`, `dashboard`, `settings/*`) skip SSR (`$withoutSsr`): they have no SEO value and are not worth the extra request.
- If the SSR server is down, pages fall back to client rendering.
    - E2E runs set `INERTIA_SSR_THROW_ON_ERROR=true`, so an SSR regression fails the suite instead of degrading silently.
    - A Playwright check verifies that public pages hydrate without mismatches. This is why `formatDate` uses an explicit locale and UTC.

**SEO**

- `SeoHead` sets the title, description, canonical URL, Open Graph and Twitter tags.
- Published lessons get schema.org `LearningResource` JSON-LD, rendered by Blade with the CSP nonce.
- `sitemap.xml` is built from the published lessons and the level pages. It is cached and invalidated by `Lesson` model events.
- `robots.txt` only allows indexing in production.

## Consequences

- The project gains one container (`ssr`) and one internal hop for public pages. Signed-in areas are unaffected.
- The locale is not part of the URL, so there are no `hreflang` alternates. This is acceptable because the indexable content (the lessons) is in English anyway. Moving to `/es/...` URLs would be the next step if the Spanish UI needed to rank separately.
- Category names and other admin-editable content are shown in Spanish only when a translation exists; otherwise the English name is shown.
