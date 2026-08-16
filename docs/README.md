# Documentation index

| Doc | Covers |
|---|---|
| [architecture.md](architecture.md) | Request lifecycle, directory map, SSR, shared Inertia props, roles/permissions, the `/cron/run` endpoint, testing layout |
| [database-schema.md](database-schema.md) | Every table, column, type, and foreign-key delete behavior — the effective schema, not a migration-by-migration diff |
| [routes.md](routes.md) | Full route reference (public, admin, JSON endpoints) — this app has no separate REST API, Inertia pages are the surface |
| [domain-model.md](domain-model.md) | Entities, relationships, the `Cat`/`Deposit` models in depth, CMS content model, seeding |
| [payments.md](payments.md) | Stripe deposit flow end-to-end: manual capture, TWINT's exception, no-hold checkout design, webhook + reconciliation job, manual admin reservations |
| [i18n.md](i18n.md) | The three translation layers (routing / content / interface) and why they must never mix |
| [admin.md](admin.md) | Admin account management, dashboard, activity log, rich text editor, notifications, generic table filtering |
| [seo.md](seo.md) | SSR, sitemap, hreflang, robots.txt/llms.txt |
| [../DEPLOY.md](../DEPLOY.md) | Deployment checklist for Infomaniak shared hosting: env vars, SSR/queue without a daemon, `.htaccess`, cron, domain change |
| [../README.md](../README.md) | Quick start, stack summary, commands |

Written from the actual state of the codebase (verified against the real
migrations, models, controllers, and routes), not from the project's
running session log — `CLAUDE.md` (gitignored, local-only) has the full
session-by-session history if you need the "why did we build it this way
instead" narrative behind a given decision.
