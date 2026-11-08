# ADR 0004 — Single-VPS Docker deployment behind Cloudflare

- **Status:** accepted · 2026-11-02 · revised 2026-11-06 (Cloudflare Tunnel on a shared VPS)

## Context

The app needs PHP-FPM, a long-running queue worker with ffmpeg, a scheduler, MySQL and Redis. Shared hosting cannot run workers or ffmpeg, and PaaS free tiers sleep and have ephemeral disks.

## Decision

- A multi-stage Dockerfile builds one PHP image (app, worker and scheduler roles) and an Nginx image with the compiled assets.
- `compose.yaml` runs everything on a Contabo VPS that is shared with other projects, so every service has a memory limit and ffmpeg is capped at two cores.
- No port is published to the internet. A `cloudflared` container opens an outbound Cloudflare Tunnel, and Cloudflare terminates HTTPS. Nginx restores the visitor IP from `CF-Connecting-IP` and passes the original scheme to PHP. In Cloudflare there is only DNS and the tunnel: no origin certificates, WAF or cache rules.
- Deploys are manual: after a PR is merged into `main`, `bin/deploy` runs on the VPS. No CI service holds an SSH key to the server; GitHub Actions only runs the checks.
- Nightly backups rotate on the VPS itself (no external service), and Laravel Pulse provides monitoring.
- The production stack is validated before release by running the Playwright suite against it locally.

## Consequences

- Reproducible environments, and the same images run locally and in production.
- No inbound firewall rules for the web, and nothing to renew (no origin certificate).
- Deploys cause a few seconds of restart, which is acceptable for a portfolio project. Blue/green would be the next step.
- The local validation already found four real production issues (see `docs/DEPLOYMENT.md`).
