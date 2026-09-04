# Changelog

All notable changes to Client Reporter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-03

The first public release.

### Added

- **Foundation** — Laravel 13 + Livewire 4 + Tailwind 4, a restrained editorial admin UI, Pint + PHPStan (level 5) + PHPUnit tooling, and CI.
- **Authentication & access** — session auth with password reset, a staff role hierarchy (Administrator, Manager, Viewer) via policies/gates, audit logging, a command palette, and user management.
- **Clients & Sites** — the client → site hierarchy with full CRUD, plus bulk site import from MainWP, ManageWP and WPMgr.
- **White-label branding** — a global → client → site branding cascade (logo, colours, typography, footers, custom CSS) with a live report-cover preview; client-facing output can be fully agency-branded.
- **Integration framework** — a Laravel-native SDK (manifest, config fields, auth methods, collectors, report blocks), a registry with Composer-package discovery, encrypted credentials, a `DateRange` value object with correct previous-period comparison, a metrics/snapshots storage model, a resilient collector runtime, and the `client-reporter:collect` scheduler command (shared-hosting friendly). Integrations can be connected once for the whole workspace and auto-matched to sites and clients, or per site.
- **Bundled integrations** — WordPress and Craft CMS (via signed, read-only companion plugins); analytics from Google Analytics 4, Google Ads, Plausible, Fathom, Matomo and Umami; Google Search Console; ecommerce from WooCommerce, Craft Commerce, Shopify and Stripe; uptime from UptimeRobot, Uptime Kuma and BetterUptime; PageSpeed Insights performance; Mailchimp; and invoice/billing sync from FreeAgent and Xero.
- **Reporting engine** — reusable templates, a drag-and-drop block builder with live preview and per-section commentary, custom date ranges with previous-period comparison, deterministic per-section plain-English summaries, and blocks for cover/contents, CMS status & updates, analytics (summary, chart, top pages, sources, countries, devices, events), search performance, ads, ecommerce, performance, uptime & incidents, forms/leads, billing, free text and closing.
- **Report outputs** — beautiful branded web reports, secure share links (expiry, password, revocation), dompdf PDF export (with optional Browsershot on a VPS), branded email delivery, and a restricted client portal. Generated reports are frozen to an immutable snapshot so shared, emailed and exported copies stay stable.
- **Billing** — a lightweight per-client invoice ledger with a report block, plus optional invoice sync from FreeAgent and Xero.
- **Installation & operations** — a browser installation wizard (SQLite/MySQL/PostgreSQL), a GitHub update checker with an admin notice, and `client-reporter:update`, `client-reporter:sync-billing` and `client-reporter:make-integration` helpers plus reusable integration contract test helpers.

[0.1.0]: https://github.com/coysh-digital/client-reporter/releases/tag/v0.1.0
