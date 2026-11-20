# ADR-0006: Company subdomains

- Status: Proposed (not built)
- Date: 2026-11-20

## Context

With per-company login methods (ADR-0005), a shared login page would have to ask for the email first to know which method to offer. A company-specific address can show the right login straight away, with the company's own name and logo.

## Decision

- Each company gets a subdomain, e.g. `cegem.skillkep.eu`, which shows only that company's login method, name and logo.
- Single database, no multi-tenant database split; the subdomain only selects the company.
- Infrastructure: wildcard DNS and a wildcard TLS certificate (Let's Encrypt DNS challenge through Traefik).
- OAuth uses one central callback URL, because Microsoft and Google don't allow wildcard redirect URIs. The callback sends the user back to their company's subdomain.
- The session cookie is scoped to the subdomain, and every request checks that the logged-in user belongs to that subdomain's company.

## Consequences

- The central callback needs a safe hand-off to the subdomain (signed, short-lived state), since the session cookie isn't shared across subdomains.
- Local testing works with `*.localhost` subdomains, which resolve without any DNS setup.
- The superadmin needs a home that isn't a company subdomain (e.g. the bare domain).
