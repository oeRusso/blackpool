# ADR 0004 — Single-VPS Docker deployment behind Cloudflare

- **Status:** accepted · 2026-11-02

## Context

The app needs PHP-FPM, a long-running queue worker with ffmpeg, a scheduler, MySQL and Redis. Shared hosting cannot run workers or ffmpeg, and PaaS free tiers sleep and have ephemeral disks.

## Decision

- A multi-stage Dockerfile builds one PHP image (app, worker and scheduler roles) and an Nginx image with the compiled assets.
- `compose.production.yaml` runs everything on a Contabo VPS. Cloudflare provides DNS, TLS (Full strict with an Origin Certificate), WAF and caching.
- GitHub Actions deploys over SSH after CI passes (`bin/deploy`).
- Nightly backups go to Backblaze B2, and Laravel Pulse provides monitoring.
- The production stack is validated before release by running the Playwright suite against it locally.

## Consequences

- Reproducible environments, and the same images run locally and in production.
- Deploys cause a few seconds of restart, which is acceptable for a portfolio project. Blue/green would be the next step.
- The local validation already found four real production issues (see `docs/DEPLOYMENT.md`).
