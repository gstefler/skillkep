# ADR-0001: Application stack

- Status: Accepted
- Date: 2026-11-20

## Context

Skill K.E.P is a corporate LMS for Hungarian companies with learner, manager and admin views. It is a rethink of `gstefler/learning_platform`. A design system (`design-system/`: petrol brand colour, IBM Plex Sans/Mono, `--kep-*` tokens) already defines the look. We want a server-rendered-feeling app with a typed, component-based frontend, and as little API plumbing as possible.

## Decision

- Backend: Laravel (13) with Fortify for authentication.
- Frontend: Inertia (v3) + Vue 3 + TypeScript, with Wayfinder for typed routes.
- UI components: shadcn-vue (reka-ui), restyled to the design system. Styling is Tailwind CSS v4, and the `--kep-*` tokens are the only source of colour (bridged to shadcn variables in `resources/css/app.css`).
- Build: Vite through vite-plus; `bun` is the only JS tool.
- Data: PostgreSQL and Redis, in local Sail and in production alike.

## Consequences

- One codebase, no separate SPA/API. Pages are Inertia pages, forms post to Laravel controllers.
- shadcn components are copied into `resources/js/components/ui` and restyled minimally, so upstream updates stay easy to diff.
- The design system stays framework-agnostic; the Vue app consumes its CSS tokens and components, not its Blade Tailwind bridge (`muted` naming clash).
