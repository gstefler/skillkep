# ADR-0003: Deployment topology

- Status: Accepted (built 2026-11-20)
- Date: 2026-11-20

## Context

The app needs a web server, queue workers (including long ffmpeg jobs, see ADR-0004), a scheduler, PostgreSQL and Redis. We want a simple, reproducible deployment without a process manager inside the container.

## Decision

One application image, run as several containers:

- **app:** FrankenPHP (Caddy) serving Laravel, without Octane. It sits behind Traefik, which terminates TLS, so Caddy runs with `auto_https off`.
- **horizon:** `php artisan horizon`, with a `video` supervisor (transcoding) and a `default` supervisor.
- **scheduler:** `php artisan schedule:work`.
- **postgres** and **redis**. Redis holds the queue, sessions and cache.

Docker's restart policy restarts crashed processes; there is no supervisord.

## Consequences

- One build, one image tag per release; containers differ only in their command.
- Scaling video transcoding is a matter of Horizon supervisor settings, not new images.
- No Octane means no long-lived app state to worry about; we can revisit it if performance needs it.

## Implementation (2026-11-20)

- `Dockerfile`: `dunglas/frankenphp` (PHP 8.5, bookworm) with ffmpeg; Composer + bun build stage (Wayfinder needs artisan during the Vite build). Runs as `www-data` on port 8080, `auto_https off`, Caddy admin API off.
- `deploy/compose.production.yml`: Dokploy Compose service (`skillkep` project) with `migrate` (one-shot, runs before the rest), `app`, `horizon` (`stop_grace_period: 65m` so encodes finish) and `scheduler`. Images are pulled on every deploy (`pull_policy: always`), so each deploy recreates the containers, which also restarts Horizon.
- PostgreSQL 18, Redis and Mailpit are separate Dokploy services on `dokploy-network`. Mailpit catches all mail (no real sending); its UI is behind HTTP basic auth.
- Storage: no object store. `/srv/skillkep/storage` on the VPS (owned by 33:33) is bind-mounted at `storage/app`. AES keys and playlists stay in `private/`; only `hls/` and `public/` are served.
- CI: `.github/workflows/deploy.yml` builds and pushes `ghcr.io/gstefler/skillkep:{latest,sha}` on `main`, then calls the Dokploy compose webhook (`DOKPLOY_DEPLOY_WEBHOOK` secret).
- Environment variables live only in Dokploy; `deploy/production.env.example` lists them without secrets.
