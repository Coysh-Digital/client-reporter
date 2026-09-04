# Development

This one's for anyone hacking on Client Reporter itself.

Client Reporter is a Laravel 13 app built with Livewire 4, Tailwind CSS 4 and PHP 8.3+. It sticks to standard Laravel conventions, with Laravel Pint for code style, Larastan/PHPStan at level 5 for static analysis, and PHPUnit for tests. See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the full contributor guide, including the branch and pull request flow.

## Local setup

The full, authoritative setup steps live in [CONTRIBUTING.md](../../CONTRIBUTING.md#development-setup). The short version:

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

Serve the app with `php artisan serve`, and run the Vite dev server with `npm run dev` for hot asset reloading. The `composer setup` script rolls the install/migrate/build steps into one command if you'd rather.

## Domain model

The core hierarchy is:

**Client → Sites → Integrations → Metrics/Snapshots → Reports**

- **Client** — an agency customer. Carries its own branding (which cascades from global → client → site).
- **Site** — a website belonging to a client. Integrations attach here (or once at the workspace level, and auto-match to sites).
- **Integration / connection** — a connected data source for a site (analytics, ecommerce, uptime, CMS, billing, …). Credentials are stored encrypted.
- **Metrics & snapshots** — the collected data. `client-reporter:collect` queues collectors for due connections; the results are stored as metrics and metric snapshots and used to build the reports and the dashboard.
- **Reports** — built from reusable templates and a drag-and-drop block builder. When a report is generated it's frozen to an immutable snapshot, so its web, shared, emailed and PDF copies all stay in sync. Reports go out as branded web pages, secure share links, PDF exports, branded email, and through the client portal.

## Roles and access

Staff accounts use a role hierarchy that's enforced through policies and gates:

- **Administrator** — full access, including settings and user management.
- **Manager** — manages clients, sites, integrations, reports and branding.
- **Viewer** — read-only staff access.

Separately, **client portal** users (role: `client`) get a locked-down, agency-branded area that shows only their own sites and reports. They pass the `access-portal` gate and don't pass `access-admin`. Route middleware (`auth`, `active`, `can:*`) enforces these boundaries; see `routes/web.php`.

## Coding standards

- **Laravel Pint** handles code style (Laravel preset plus `declare_strict_types`, alphabetically-ordered imports, no unused imports — see `pint.json`). Run `./vendor/bin/pint` to fix things, or `./vendor/bin/pint --test` to check the way CI does.
- **PHPStan / Larastan at level 5** has to pass with no new errors (`phpstan.neon` analyses `app/` and `tests/`, with model-property checks on). Run `./vendor/bin/phpstan analyse --memory-limit=512M`.
- **PHPUnit** for tests — the suite's at 267 tests right now. Any new behaviour needs to come with tests.

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

Please make sure `composer check` passes before you open a pull request.

## Project structure

The application code lives under `app/`:

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

The product-specific configuration is in `config/client-reporter.php` (see [Configuration](../configuration/README.md)). Scheduled work is defined in `routes/console.php`; routes in `routes/web.php`.

## dompdf-safe report views

PDF export defaults to the **dompdf** renderer, which only supports a subset of modern CSS. So when you're writing report blocks and their views, keep the markup dompdf-friendly:

- Stick to simple, table- and block-based layouts rather than CSS grid/flex tricks that dompdf can't render.
- Don't lean on features Browsershot would render but dompdf wouldn't — the same view has to produce an acceptable PDF under dompdf.
- Test both the web preview and the PDF export (`/reports/{report}/pdf`) whenever you change a block.

Browsershot (headless Chromium) is there as an opt-in on a VPS for pixel-perfect output, but your blocks should still render correctly under dompdf. See [Configuration](../configuration/README.md#pdf-rendering).

## Building an integration

Integrations are first-class, discoverable Composer packages. You can scaffold one with:

```bash
php artisan client-reporter:make-integration "Matomo"
```

That gives you the skeleton — manifest, config fields, auth method, collectors, metrics and report blocks — ready for you to fill in. Third-party integrations are picked up automatically from any installed package that declares an `extra.client-reporter.integrations` array, so they don't need any core changes. See [Creating an integration](../creating-an-integration/README.md) for the full SDK guide, and the contract test helpers for testing your integration.

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the branch naming, commit and pull request process, the issue templates (bug report, feature request, integration proposal), and what's deliberately out of scope.
