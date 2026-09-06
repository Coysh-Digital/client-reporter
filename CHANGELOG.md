# Release Notes for Client Reporter

## Unreleased

- Fixed a stored cross-site scripting hole in report branding: heading/body fonts must now come from the built-in catalogue and custom CSS is validated (no markup, `@import` or `url()`) and filtered again at render time, so nothing typed into the branding form can run script on a shared report, portal or preview page.
- Added a strict `Content-Security-Policy` (no scripts), `nosniff`, `no-referrer`, `no-store` and `noindex` headers to every client-facing report page.
- Added an outbound-request guard: every URL staff enter (site addresses, self-hosted analytics and monitoring, companion plugins, import sources) must be `http(s)` and resolve to a public address, with redirects re-checked hop by hop. Self-hosted services on a private network can be allow-listed with `CLIENT_REPORTER_ALLOWED_HOSTS` or `CLIENT_REPORTER_ALLOW_PRIVATE_URLS`. Cached site favicons are no longer stored as SVG.
- Hardened the installer: it refuses to run again once installed (including via background requests), is rate limited, validates database settings, and no longer keeps the database or administrator password in the page between steps.
- Bound OAuth connections (Google, Xero, FreeAgent) to the browser session with a single-use, ten-minute `state` nonce so a captured callback link cannot attach someone else's account.
- Raised the minimum password length to 12 for the installer, user management and password reset; rate limited password-reset requests; cleared submitted passwords from form responses; and made the two-factor challenge expire after five minutes and re-check that the account is still active.
- Share-link passwords must be at least 10 characters, guesses are rate limited per link, a link is revoked after 20 wrong guesses, and an unlock now lasts 30 minutes.
- MCP API tokens now expire (30 days by default, `--expires-days` to change) and can be revoked with `client-reporter:mcp-token --revoke`; deactivating or deleting a user revokes their tokens, the endpoint is rate limited, and tools deny access rather than allow it when no user can be resolved.
- Added `TRUSTED_PROXIES` support and host-header validation, so installs behind a proxy or CDN keep correct client addresses for rate limiting and the audit log.
- Changed `.env.example` to production-safe defaults (`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`).
- The connector connection code is now shown once when generated and can be regenerated from the connection page; a deactivated user is signed out on their next action, not only their next page load; a client portal user can no longer open a report that has been reverted to draft; and integration setup steps from third-party extensions are rendered with a strict HTML allow-list.
- Added an editable report language file: every fixed word and phrase on a client-facing report is now customisable (and translatable) via a git-ignored `config/report-language.local.php` that survives updates, deep-merged over the shipped `config/report-language.php` defaults. See [Branding → Report wording and translation](docs/branding/README.md#report-wording-and-translation).
- Enlarged the Lighthouse score gauges and rendered the gauge and chart-axis numbers in a sans-serif face; report metric deltas now show as `+`/`-` prefixes.
- Added opt-in weekly, monthly and quarterly report scheduling; `client-reporter:generate-scheduled` auto-generates each scheduled site's report once its period closes, ready to review and send.
- Added a report-history list and per-site summaries (health, connected integrations, report count, schedule, latest report) plus a reporting totals card to the client page.
- Added update-safe custom integrations: the git-ignored `extensions/` directory is autoloaded and auto-discovered, and an optional git-ignored `config/client-reporter.local.php` can register classes explicitly.
- Added an `AGENTS.md` guide for contributors and AI agents (`CLAUDE.md` points to it).
- Fixed the dashboard "Needs attention" list flagging every active site for the current, still-open period; it now surfaces only scheduled reports that are generated but not yet sent.
- Fixed the installation wizard reloading the same step instead of advancing: the "not installed" gate was redirecting Livewire's own (hash-prefixed) update requests back to the wizard. Sessions and cache also now default to file storage so they work before the database is set up.
- Fixed the FreeAgent contact import stopping at the first 25 contacts; it now pages through every contact. When mapping, each contact can be imported as a new client (individually or all unmapped at once), not just matched or skipped.
- Added recurring invoice schedules from FreeAgent so you can see what's coming up for a client. They appear as an "Upcoming (recurring)" list on the client's billing panel and are deliberately kept out of reports.
- Added an Activity page (under Setup) showing background data collection live — queued, running, recently finished and failed runs, with durations, record counts and error messages. "Collect now" on a site now runs in the background instead of blocking the page.
- Fixed queued data collection failing on PHP 8.4+: the collection job's property name collided with the queue trait's, which is a fatal error on newer PHP. Scheduled/queued collection now runs.
- Added a per-integration snapshot to the site page: each connected service shows when it was last collected, its latest metrics, and a small Chart.js chart of its headline metric across periods.
- Fixed Uptime Kuma reporting zeros for uptime and response time. It now reads Kuma's own uptime-ratio and average-response-time aggregates (preferring the 30-day window), so figures match the Kuma dashboard from the first collection instead of slowly building from samples.
- Added two consolidated report components used in the default template: "Site traffic" (headline metrics, a visitors trend, and top pages/referrers/countries/devices in one panel) and "Uptime & performance" (availability metrics, a daily uptime strip, Lighthouse scores and incidents). The previous separate analytics and uptime blocks remain available in the builder.
- Collected all four Lighthouse scores (performance, accessibility, best practices, SEO) from PageSpeed, and TLS certificate-expiry alerts from Uptime Kuma — both surfaced in the Uptime & performance report component. The visitors trend now reads as a filled area chart.
- Added workspace-level PageSpeed: connect the (optional) API key once and enable measurement across your sites in one step, each measured by its own address.
- Added brand logos for Mailchimp, Uptime Kuma, UptimeRobot, FreeAgent and Google Ads on the Integrations page (falling back to a letter mark until the logo file is present).
- Added automatic site favicons: each active site's favicon is fetched from the site, cached, and shown on the sites list and site page; refreshed weekly.
- Google Search Console errors now include Google's own reason (e.g. the API not being enabled) instead of a bare HTTP code, so a 403 on "Find sites" is actionable.
- Made the scheduled queue worker resilient to being out-of-memory-killed: it recycles on a memory ceiling and no longer wedges the queue on a stale lock if the host kills it.
- The site "Connect a service" list now hides services already connected here, connected once for the whole workspace, or that are workspace-only.
- The Activity page now lists jobs currently on the queue (waiting and running), and a live queue monitor in the sidebar shows at a glance whether background jobs are idle, queued or running.
- Reorganised the Activity page into Recent runs / Queued / Failed jobs tabs. Failed jobs can be retried or dismissed (individually or all at once), and a stuck queue can be cleared.
- Report trends now render as real vector line graphs (visitors over time, and the daily-visitors block) in both the web report and the PDF.
- Extended the line graphs to the daily-uptime trend and a Lighthouse performance-score history in the Uptime & performance component (and the standalone uptime/performance blocks); these use a fitted y-axis so small movements near the top of the range are visible.

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
