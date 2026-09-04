# Release Notes for Client Reporter

## Unreleased

- Added opt-in weekly, monthly and quarterly report scheduling; `client-reporter:generate-scheduled` auto-generates each scheduled site's report once its period closes, ready to review and send.
- Added a report-history list and per-site summaries (health, connected integrations, report count, schedule, latest report) plus a reporting totals card to the client page.
- Added update-safe custom integrations: the git-ignored `extensions/` directory is autoloaded and auto-discovered, and an optional git-ignored `config/client-reporter.local.php` can register classes explicitly.
- Added an `AGENTS.md` guide for contributors and AI agents (`CLAUDE.md` points to it).
- Fixed the dashboard "Needs attention" list flagging every active site for the current, still-open period; it now surfaces only scheduled reports that are generated but not yet sent.
- Fixed the installation wizard reloading the same step instead of advancing: the "not installed" gate was redirecting Livewire's own (hash-prefixed) update requests back to the wizard. Sessions and cache also now default to file storage so they work before the database is set up.
- Fixed the FreeAgent contact import stopping at the first 25 contacts; it now pages through every contact. When mapping, each contact can be imported as a new client (individually or all unmapped at once), not just matched or skipped.
- Added recurring invoice schedules from FreeAgent so you can see what's coming up for a client. They appear as an "Upcoming (recurring)" list on the client's billing panel and are deliberately kept out of reports.

## 0.1.0-alpha.1 - 2026-09-04

- Added the foundation: Laravel 13, Livewire 4 and Tailwind 4, a restrained editorial admin UI, and Pint + PHPStan (level 5) + PHPUnit tooling with CI.
- Added authentication and access: session auth with password reset, an Administrator/Manager/Viewer role hierarchy, audit logging, a command palette, and user management.
- Added the client → site hierarchy with full CRUD, plus bulk site import from MainWP, ManageWP and WPMgr.
- Added white-label branding with a global → client → site cascade (logo, colours, typography, footers, custom CSS) and a live report-cover preview.
- Added the integration SDK (manifest, config fields, auth methods, collectors, report blocks), a registry with Composer-package discovery, encrypted credentials, scheduled collection via `client-reporter:collect`, and workspace-wide "connect once" connections auto-matched to sites and clients.
- Added bundled integrations: WordPress and Craft CMS (read-only companion plugins); Google Analytics 4, Google Ads, Plausible, Fathom, Matomo and Umami; Google Search Console; WooCommerce, Craft Commerce, Shopify and Stripe; UptimeRobot, Uptime Kuma and Better Uptime; PageSpeed Insights; Mailchimp; and FreeAgent and Xero billing sync.
- Added the reporting engine: reusable templates, a drag-and-drop block builder with live preview and per-section commentary, previous-period comparison, deterministic plain-English summaries, and blocks for cover/contents, CMS status and updates, analytics, search, ads, ecommerce, performance, uptime, forms/leads, billing, text and closing.
- Added report outputs: branded web reports, secure share links (expiry, password, revocation), dompdf PDF export (Browsershot optional on a VPS), branded email delivery, and a restricted client portal — all frozen to an immutable snapshot so shared copies stay stable.
- Added a lightweight per-client invoice ledger with a billing report block.
- Added a browser installation wizard (SQLite/MySQL/PostgreSQL), a GitHub update checker with an admin notice, and the `client-reporter:update`, `client-reporter:sync-billing` and `client-reporter:make-integration` commands.
- Added a built-in read-only MCP server so an AI assistant can query clients, sites, reports and metrics, over stdio (`php artisan mcp:start client-reporter`) and an authenticated HTTP endpoint (a Sanctum `mcp:read` token). See [docs/mcp](docs/mcp/README.md).
