# ADR 0001 — Rebuild on the Laravel 13 Vue starter kit

- **Status:** accepted · 2026-11-02

## Context

The legacy app ran Laravel 11 + Jetstream with Inertia 1, Vue in plain JavaScript and Tailwind 3. It had very little domain code (two models with CRUD, half of which were empty stubs). Upgrading Jetstream in place would have meant keeping teams, API tokens and its Blade/Vue scaffolding, none of which the product uses.

## Decision

Start from the official Laravel 13 Vue starter kit:

- Fortify with 2FA and passkeys;
- Inertia 3;
- Vue 3.5 + TypeScript;
- shadcn-vue;
- Tailwind 4;
- Wayfinder typed routes.

Port the domain onto it. The legacy code stays available under the `legacy-baseline` git tag.

## Consequences

- TypeScript and Wayfinder catch broken routes and props at build time.
- Less code to maintain: no unused Jetstream features.
- Every legacy page had to be rewritten, which was acceptable given its size and its bugs.
