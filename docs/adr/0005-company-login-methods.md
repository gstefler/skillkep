# ADR-0005: Per-company login methods

- Status: Proposed (not built)
- Date: 2026-11-20

## Context

Client companies often already have Microsoft 365 or Google Workspace and would rather their employees log in with those accounts than with yet another password. Others have no identity provider. Self-registration stays off (ADR-0002).

## Decision

- When the superadmin creates a company, they pick its login method: **Microsoft 365**, **Google Workspace**, or **email + password**.
- Each company has an email allowlist (its provisioned users). There are no invitations: a user who signs in through the provider is let in only if their account is on the list.
- Implementation uses Socialite: `socialiteproviders/microsoft` and the built-in Google driver.
- **Microsoft 365:** one multi-tenant Entra app. On login, check that the token's tenant id (`tid`) matches the company's tenant, and bind the user's `oid` on first login. Never trust the email claim alone. The company's IT may need to grant admin consent. Publisher verification is recommended. Client secrets expire (at most 2 years), so track the renewal.
- **Google Workspace:** an External OAuth consent screen. Check that `hd` equals the company's domain and bind the user's `sub` on first login. Reject plain gmail.com accounts.
- Keep a password fallback for the superadmin and for each company's contact person, so a broken provider setup can't lock everyone out.

## Consequences

- Users stay keyed by email, so switching a company's login method later is easy.
- We need columns for the company's method, tenant id / domain, and per-user provider ids (`oid` / `sub`).
- Local testing: `http://localhost` redirect URIs are allowed by both providers; a free Azure account gives a test Entra tenant; Google `hd` needs a real Workspace (a trial is enough). Feature tests mock Socialite.
