# Development

This section is for developers working on Client Reporter itself.

Client Reporter is a Laravel 13 application using Livewire 4, Tailwind CSS 4 and PHP 8.3+. It follows standard Laravel conventions, with Laravel Pint for code style, Larastan/PHPStan at level 5 for static analysis, and PHPUnit for tests. See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the full contributor guide, including the branch and pull request flow.

## Local setup

The full, authoritative setup steps live in [CONTRIBUTING.md](../../CONTRIBUTING.md#development-setup). In short:

```bash
git clone https://github.com/coysh-digital/client-reporter.git
cd client-reporter
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate      # default SQLite needs no configuration
npm run build
```

Serve the app with `php artisan serve` and run the Vite dev server with `npm run dev` for hot asset reloading. The `composer setup` script bundles the install/migrate/build steps into one command.

## Domain model

The core hierarchy is:

**Client → Sites → Integrations → Metrics/Snapshots → Reports**

- **Client** — an agency customer. Carries its own branding (which cascades from global → client → site).
- **Site** — a website belonging to a client. Integrations attach here (or once at the workspace level and auto-match to sites).
- **Integration / connection** — a connected data source for a site (analytics, ecommerce, uptime, CMS, billing, …). Credentials are stored encrypted.
- **Metrics & snapshots** — the collected data. `client-reporter:collect` queues collectors for due connections; results are stored as metrics and metric snapshots and used to build reports and the dashboard.
- **Reports** — built from reusable templates and a drag-and-drop block builder. A generated report is frozen to an immutable snapshot so its web, shared, emailed and PDF copies stay stable. Reports are delivered as branded web pages, secure share links, PDF exports, branded email, and via the client portal.

## Roles and access

Staff accounts use a role hierarchy enforced through policies and gates:

- **Administrator** — full access, including settings and user management.
- **Manager** — manages clients, sites, integrations, reports and branding.
- **Viewer** — read-only staff access.

Separately, **client portal** users (role: `client`) get a restricted, agency-branded area showing only their own sites and reports. They pass the `access-portal` gate and do not pass `access-admin`. Route middleware (`auth`, `active`, `can:*`) enforces these boundaries; see `routes/web.php`.

## Coding standards

- **Laravel Pint** enforces code style (Laravel preset plus `declare_strict_types`, alphabetically-ordered imports, no unused imports — see `pint.json`). Run `./vendor/bin/pint` to fix, `./vendor/bin/pint --test` to check as CI does.
- **PHPStan / Larastan at level 5** must pass with no new errors (`phpstan.neon` analyses `app/` and `tests/`, with model-property checks on). Run `./vendor/bin/phpstan analyse --memory-limit=512M`.
- **PHPUnit** for tests — the suite currently has 267 tests. New behaviour must be covered by tests.

### Running the check suite

Run everything CI runs with one command:

```bash
composer check     # pint --test, phpstan, then php artisan test
```

Or the individual tools:

```bash
php artisan test                                        # tests
./vendor/bin/pint            # fix style   (--test to check only)
./vendor/bin/phpstan analyse --memory-limit=512M        # static analysis
```

Make sure `composer check` passes before opening a pull request.

## Project structure

Application code lives under `app/`:

| Path | Contains |
| --- | --- |
| `app/Livewire` | Livewire components — the admin UI, install wizard, settings, clients, sites, reports, integrations, portal. |
| `app/Integrations` | The Integration SDK and every first-party integration (manifest, config fields, auth, collectors, report blocks). |
| `app/Reporting` | The reporting engine — report blocks (`Reporting/Blocks/…`), builders and rendering. |
| `app/Console/Commands` | Artisan commands: `client-reporter:collect`, `:check-updates`, `:sync-billing`, `:update`, `:make-integration`. |
| `app/Jobs` | Queued jobs, including `RunConnectorCollection`. |
| `app/Http` | Controllers for OAuth callbacks, public/portal reports and PDF export. |
| `app/Models` | Eloquent models (Client, Site, SiteIntegration, Metric, MetricSnapshot, Report, User, Setting, BrandingProfile, …). |
| `app/Support` | Cross-cutting helpers (`Settings`, `EnvWriter`, `UpdateChecker`, `AuditLogger`, `DateRange`, …). |
| `app/Billing`, `app/Importers`, `app/Enums`, `app/Mail`, `app/Providers` | Billing ledger, bulk site importers, enums, mailables and service providers. |

Configuration specific to the product is in `config/client-reporter.php` (see [Configuration](../configuration/README.md)). Scheduled work is defined in `routes/console.php`; routes in `routes/web.php`.

## dompdf-safe report views

PDF export defaults to the **dompdf** renderer, which supports only a subset of modern CSS. When authoring report blocks and their views, keep the markup dompdf-friendly:

- Prefer simple, table- and block-based layouts over CSS grid/flex tricks that dompdf cannot render.
- Avoid relying on features Browsershot would render but dompdf would not; the same view must produce an acceptable PDF under dompdf.
- Test both the web preview and the PDF export (`/reports/{report}/pdf`) when changing a block.

Browsershot (headless Chromium) is available as an opt-in on a VPS for pixel-perfect output, but blocks should still render correctly under dompdf. See [Configuration](../configuration/README.md#pdf-rendering).

## Building an integration

Integrations are first-class, discoverable Composer packages. Scaffold one with:

```bash
php artisan client-reporter:make-integration "Matomo"
```

This generates the skeleton — manifest, config fields, auth method, collectors, metrics and report blocks — for you to fill in. Third-party integrations are discovered automatically from installed packages that declare an `extra.client-reporter.integrations` array, so they need no core changes. See [Creating an integration](../creating-an-integration/README.md) for the full SDK guide, and the contract test helpers for testing your integration.

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the branch naming, commit and pull request process, the issue templates (bug report, feature request, integration proposal), and what is deliberately out of scope.
