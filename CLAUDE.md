# Skill K.E.P

A corporate learning portal (LMS) for Hungarian companies, with separate views for learners, managers and administrators. It is a rethink of the earlier `gstefler/learning_platform` project.

- Repo: `gstefler/skillkep`, default branch `main`.
- The owner (Gábor) writes in Hungarian. Reply to him in Hungarian. The app UI is Hungarian too.
- Every page requires login. There is no public landing page and **no self-registration**: accounts are provisioned by the organisation/admin.

## Stack

- **Backend:** Laravel 13 (PHP ^8.3; 8.5 locally), Fortify (only login + password reset are enabled), Inertia v3, Wayfinder (typed routes generated into `resources/js/{routes,actions,wayfinder}`, gitignored).
- **Frontend:** Vue 3 + TypeScript, shadcn-vue (reka-ui) in `resources/js/components/ui`, Tailwind CSS v4, `@lucide/vue` icons, vue-sonner toasts.
- **Build:** Vite through vite-plus (`vp`). Fonts are self-hosted by the laravel-vite-plugin `bunny()` fonts helper, configured in `vite.config.ts`.
- **Local dev runs in Laravel Sail** (Docker): PostgreSQL, Redis (sessions, cache), Mailpit (mail UI on :8025) and pgweb (web PostgreSQL client on http://localhost:8081, bound to localhost only, auto-connected to the app DB; port via `FORWARD_PGWEB_PORT`). See the Tooling rules: every command runs through Sail. `node_modules` is installed inside Sail (Linux bindings), so run `sail bun install` rather than a host install.
- This started from the Laravel Vue starter kit. Its optional features were stripped with `laravel/chisel`: **registration, email verification, password confirmation, two-factor auth and passkeys are removed** (pages, components, routes, migrations, tests; see ADR-0002). Do not reintroduce them unless asked. The `verified` route middleware is harmless (User does not implement `MustVerifyEmail`).

## Tooling rules

- **Local development always runs in Sail.** Run PHP, Composer, Artisan and bun through `./vendor/bin/sail` (alias `sail`), never on the host: `sail artisan …`, `sail composer …`, `sail bun …`, `sail test`. Start the stack with `sail up -d`. The only exception is git.
- **JS: always `bun`. Never `node`, `npm` or `npx`** (use `sail bunx` if you need it).
- `sail composer setup`: first install (composer, `.env`, key, migrate, `bun install`, build).
- `sail composer dev`: dev servers (`php artisan dev`).
- `sail bun run build`: production assets.
- `sail bun run check` / `sail bun run check:fix`: vp lint and format (oxlint/oxfmt; 4-space indent, single quotes, Tailwind class sorting).
- `sail bun run types:check`: vue-tsc.
- `sail artisan test`: PHPUnit feature tests (tests guarded by a disabled Fortify feature are skipped, which is expected). `sail composer test` also runs Pint and PHPStan.
- `sail composer ci:check`: everything above.
- Seeded accounts after `sail artisan db:seed` (all with password `password`, local only): `superadmin@example.com` (superadmin), `admin@example.com` (admin of Nagy és Társa Kft.), `test@example.com` (learner of the same company).
- Superadmins are created with `sail artisan user:create-superadmin` (prompts, or `--name= --email=`); the generated password is printed once and emailed.
- Run `sail bun run check`, `sail bun run types:check` and `sail artisan test` before you commit.

## Layout

```
docs/adr/                Architecture decision records (see "Architecture decisions")
design-system/           Framework-agnostic design system v1 (source of truth for the look)
  css/tokens.css         Primitive scales, semantic --kep-* tokens, dark theme, density, reduced motion
  css/components.css     .kep-* component classes (@layer components)
  css/tailwind-theme.css Tailwind bridge for Blade (NOT used by the Vue app, see below)
  index.html             Live preview: open in a browser
  README.md              Principles, typography, writing style (Hungarian)
resources/css/app.css    Tailwind v4 + design tokens + the shadcn-vue variable bridge
resources/js/
  app.ts                 Inertia bootstrap; picks the layout by page name (auth/* → AuthLayout, settings/* → App+Settings, else AppLayout)
  layouts/               AppLayout → app/AppSidebarLayout (shell); AuthLayout → auth/AuthSimpleLayout; settings/Layout
  components/            App components (AppSidebar, NavMain, PageHeader, StatCard, ProgressBar, EmptyState…)
  components/ui/         shadcn-vue components, already restyled to the design system (see below)
  pages/                 Inertia pages (Dashboard, auth/Login|ForgotPassword|ResetPassword, settings/Profile|Security|Appearance)
routes/web.php           "/" redirects to /dashboard; the auth middleware sends guests to /login
routes/settings.php      Profile, security, appearance
```

## Design system integration (read this before you touch styles)

- **Tokens are the only source of colour.** `app.css` imports `design-system/css/tokens.css` and `components.css`, then maps the shadcn variables (`--background`, `--primary`, `--muted`, `--sidebar-*`…) to `--kep-*` tokens. Don't write hex, oklch or Tailwind palette colours (`text-red-600`, `neutral-*`). Use the semantic utilities instead.
- **Utilities available:**
    - shadcn names: `bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `bg-muted`, `bg-primary`, `text-primary-foreground`, `bg-secondary`, `border-border`, `border-input`, `ring-ring`, `bg-destructive`.
    - Design-system extras: `bg-surface(-raised|-sunken)`, `bg-bg-subtle`, `border-border-strong`, `text-subtle`, `text-disabled`, `text-link`, `bg-primary-subtle`, `text-primary-subtle-foreground`, `bg-primary-hover`, `text|bg-success(-subtle)`, `warning`, `info`, `destructive-subtle`, `achievement(-subtle|-foreground)`, `bg-track`, `chart-1…6`.
- **Naming clash:** in `design-system/css/tailwind-theme.css`, `muted` is a text colour, while shadcn uses `muted` as a background. That is why the Vue app does not import that file. Keep the shadcn meaning.
- **Type scale (UI baseline 14px):** `text-2xs` 11, `text-xs` 12, `text-sm` 14 (body default), `text-base` 16 (reading text), `text-lg` 18, `text-xl` 22, `text-2xl` 28 (page title), `text-3xl` 36 (KPI). Font: IBM Plex Sans; IBM Plex Mono for IDs and timers. Use `tabular-nums` for figures.
- **Radius:** `rounded-md` (6px) for controls, `rounded-lg` (10px) for cards. `rounded-xl` is aliased to 10px.
- **Density:** control heights use `h-(--kep-control-h|-sm|-lg)` and card padding uses `p-(--kep-card-pad)`, so `data-density="compact"` on `<html>` shrinks them.
- **Dark mode:** `data-theme="light|dark"` on `<html>`. With no attribute, the theme follows the OS. The `dark:` variant uses the same rule (custom variant in `app.css`). The `.dark` class is **not** used. The server renders the attribute from the `appearance` cookie in `resources/views/app.blade.php`, and `useAppearance.ts` updates it on the client.
- **Tenant branding:** override the whole `--kep-brand-50…950` scale on the `<html>` element itself. A deeper wrapper won't work.
- **Restyled shadcn variants:**
    - Button: `default` (primary), `outline` (neutral), `secondary` (tinted subtle), `ghost`, `destructive`, `link`.
    - Badge: `default` (brand), `secondary`, `success`, `warning`, `destructive`, `info`, `achievement`, `outline`.
    - Alert: `default`, `info`, `success`, `warning`, `destructive`.
    - If you add new shadcn components (`bunx shadcn-vue@latest add …`), restyle them the same way: no `dark:bg-input/30`, focus `ring-ring/25` (inputs) or `ring-2 ring-offset-2` (buttons), and density heights.
- **UX rules from the design system:**
    - Deadlines come first.
    - Show status by colour, icon and text together.
    - Gold (`achievement`) marks results only, never actions.
    - Button labels start with a verb ("Képzés indítása").
    - Date format: `2026. 12. 15.`. Percent format: `87,4 %`. Names: family name first.
    - WCAG 2.1 AA, visible focus, 44px touch targets.

## Current state (2026-11)

- Done: the design-system theme, app shell (brand cell, topbar with breadcrumbs, role-dependent sidebar `NavGroup`s in `AppSidebar.vue`), the guest/auth layout, Hungarian auth pages and shell, and user/company management (below).
- **Roles** (`App\Enums\UserRole`, `users.role`): `learner` sees only the empty course list (`/courses`; `/dashboard` redirects there). `admin` belongs to one company and manages (create, view, reset password, delete) only that company's learners. `superadmin` manages companies, admins and all learners; there is no UI to create a superadmin. Access: `admin` / `superadmin` route middleware plus `UserPolicy::manage` and `User::manageableLearnersFor`.
- Flow: superadmin creates a company, adds learners and admins to it (company page), later assigns courses. A new account gets a generated password: shown once in `GeneratedPasswordBanner` (Inertia flash `generatedPassword`) and emailed by `UserProvisioningService`. Companies with users cannot be deleted.
- Shared UI for these screens: `DataTable`, `PaginationLinks`, `ConfirmAction`, `NativeSelect`, `CompanyForm`. Wayfinder must be generated with `--with-form` (`sail artisan wayfinder:generate --with-form`; the Vite plugin does it in dev).
- **Not done yet:**
    - The settings pages (Profile, Security = password change, Appearance) are still in English.
    - Course domain models (courses, assignments to companies/learners, progress, certificates) do not exist yet; course assignment is the next step.
    - Only `lang/hu/validation.php` exists (it covers the new forms); the auth/password/pagination lang files are still missing, so those messages fall back to English.

## Architecture decisions

Decisions are recorded as ADRs in `docs/adr/` (index in `docs/adr/README.md`). Read the relevant ADR before you work in its area, and add a new numbered ADR when you make a decision of the same weight. In short:

- **0001 Stack (accepted):** Laravel + Fortify, Inertia + Vue 3 + TS, shadcn-vue themed to the design system (petrol, IBM Plex, Tailwind v4), PostgreSQL + Redis.
- **0002 No self-registration (accepted):** every page requires login; no registration, email verification, 2FA or passkeys.
- **0003 Deployment (accepted, not built):** one image, containers for app (FrankenPHP/Caddy, no Octane, behind Traefik TLS), Horizon, scheduler, PostgreSQL, Redis; Docker restart instead of supervisord.
- **0004 Video (accepted, not built):** HLS via queued ffmpeg, AES-128 key per video; Laravel serves playlist + key after the course-assignment check, Caddy serves segments statically.
- **0005 Company login methods (proposed):** per company M365, Google Workspace or email + password, with an email allowlist; verify `tid`/`hd` and bind `oid`/`sub`, never trust the email claim alone.
- **0006 Company subdomains (proposed):** `cegem.skillkep.eu` shows that company's login and branding; single DB, wildcard DNS/TLS, one central OAuth callback.

## Conventions

- Don't push or open PRs without explicit approval from the owner.
- Commit messages are in English.
- Keep `components/ui` restyles minimal and token-based, so future shadcn updates stay easy to diff.
- Laravel Boost (`laravel/boost`, dev dependency) may add its own guideline block to this file. Keep project-specific notes outside that block.
