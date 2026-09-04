# Client Reporter — contributor & agent guide

Open-source, self-hosted, white-label client reporting for web agencies. One installation belongs to
one agency (single-tenant, not multi-tenant SaaS). It connects the services behind a client's site,
collects data on a schedule, and renders fully branded reports as web pages, PDFs, emails and a
client portal.

## Stack

- PHP 8.3+, Laravel 13, Livewire 4 (class-based components), Tailwind CSS 4 + Vite 8.
- SQLite by default; MySQL/MariaDB and PostgreSQL supported. Database drivers for cache/session/queue
  (no Redis required).
- PDF via `spatie/laravel-pdf`: **dompdf** by default (shared-host safe), Browsershot optional on a VPS.

## Common commands

```bash
composer check      # Pint (--test) + PHPStan + PHPUnit — run this before every commit
composer test       # PHPUnit only
composer pint       # auto-fix code style
composer stan       # PHPStan (larastan, level 5, analyses app + tests)
npm run build       # build front-end assets
```

Local dev uses DDEV (`ddev start`, https://client-reporter.ddev.site). CI runs the same
`pint --test` + PHPStan + `artisan test` matrix on PHP 8.3/8.4/8.5.

## Conventions that matter

- **PHPUnit, not Pest.** Tests live in `tests/Feature` and `tests/Unit`.
- **PHPStan level 5.** Note larastan mis-types magic model properties and `Model::find()` as non-null —
  use explicit `!== null` checks / `->first()` instead of `?->` + `??` on those.
- **Report views must be dompdf-safe.** No flex/grid, and **no inline `<svg>`** (dompdf renders neither
  here). Charts and icons are built from plain HTML/CSS primitives — see `App\Support\ReportIcons`
  and the table-based bar chart. Report Blade lives in `resources/views/reports/`.
- **Fixed, not configurable.** Don't add a Settings override unless configurability was asked for.
- **Fonts** are self-hosted via Vite; layouts call `{{ Vite::fonts() }}` in `<head>`.

## Architecture

Core model: **Client → Sites → Integrations → collected Metrics → Reports**.

- `app/Integrations/` — the integration SDK. Each integration extends
  `App\Integrations\Contracts\Integration` (manifest, config fields, setup steps, `verify()`,
  collectors, report blocks). Scaffold new ones with `php artisan client-reporter:make-integration`.
  Copy an existing analytics provider (e.g. Plausible) as the template. Integrations are registered in
  `config/client-reporter.php`; third-party ones ship as Composer packages discovered via
  `extra.client-reporter.integrations`.
- `app/Reporting/` — the reporting engine (`ReportGenerator`, `ReportResolver`, `ReportDocument`) and
  `Blocks/`. `ReportGenerator::generate()` collects the exact report + comparison period, resolves
  branding, and **freezes** everything into an immutable `ReportRender` so shared/emailed/exported
  reports stay stable.
- `app/Console/Commands/CollectData.php` (`client-reporter:collect`) runs hourly from a single cron
  (`* * * * * php artisan schedule:run`) and queues collection jobs; shared-host friendly.

## Companion plugins

WordPress and Craft integrations read data over HMAC-signed requests from read-only companion plugins
in sibling repos (`coysh-digital/client-reporter-wordpress`, `coysh-digital/client-reporter-craft`).
Client Reporter never writes to clients' sites.
