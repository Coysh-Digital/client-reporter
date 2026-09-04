# Changelog

All notable changes to Client Reporter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Scheduled reports** — sites can now opt into a reporting schedule (weekly, monthly or quarterly; off by default). When a period closes, `client-reporter:generate-scheduled` (run daily) auto-generates that site's report — pulling the data and freezing the snapshot — so it's ready for you to review and send. Sending stays manual.
- **Client page: report history & site summaries** — a client's page now shows a report-history list across all its sites (period, status, generated date) and a per-site summary (health, connected integrations, report count, schedule and latest-report status), plus a reporting totals card.
- **Update-safe custom integrations** — the git-ignored `extensions/` directory is now autoloaded and auto-discovered, so a custom integration dropped there survives updates with no edit to any tracked file. An optional git-ignored `config/client-reporter.local.php` can register classes explicitly. `make-integration` scaffolds into `extensions/` accordingly.

### Fixed

- **Dashboard "Needs attention"** no longer flags every active site for the current, still-open period. It now surfaces only scheduled reports that have been generated but not yet sent, and the "Reports" tile/panel reflect scheduled sites rather than all sites.

## [0.1.0-alpha.1] - 2026-09-04

The first public (alpha) release. Feature-complete for an MVP but early — expect rough edges, and test before pointing real clients at it.

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
- **MCP server** — a built-in, read-only [Model Context Protocol](https://modelcontextprotocol.io) server so an AI assistant can query your clients, sites, reports and metrics in plain English. Seven read-only tools (dashboard, clients, sites, site detail, reports, report data, site metrics), exposed over both a local (stdio) transport (`php artisan mcp:start client-reporter`) and an authenticated HTTP endpoint (Sanctum token with an `mcp:read` ability, minted via `php artisan client-reporter:mcp-token`). Access mirrors the app's staff roles and never exposes integration credentials. See [docs/mcp](docs/mcp/README.md).

[0.1.0-alpha.1]: https://github.com/coysh-digital/client-reporter/releases/tag/v0.1.0-alpha.1
