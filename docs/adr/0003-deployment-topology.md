# ADR-0003: Deployment topology

- Status: Accepted (not built yet)
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
