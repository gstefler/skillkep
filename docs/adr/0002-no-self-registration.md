# ADR-0002: No self-registration

- Status: Accepted
- Date: 2026-11-20

## Context

Users are employees of client companies. Accounts belong to an organisation, which decides who gets access. A public sign-up page, or account-level security features we don't need yet, would only add surface area.

## Decision

- Every page requires login. There is no public landing page; `/` redirects to the dashboard and guests are sent to `/login`.
- No self-registration. Accounts are provisioned by a superadmin or a company admin, with a generated password that is shown once and emailed.
- Fortify only provides login and password reset. Registration, email verification, password confirmation, two-factor auth and passkeys were removed (with `laravel/chisel`) and are not reintroduced unless decided otherwise.

## Consequences

- The `verified` middleware is harmless (User doesn't implement `MustVerifyEmail`).
- Stronger authentication is expected to come from company identity providers (ADR-0005) rather than in-app 2FA.
