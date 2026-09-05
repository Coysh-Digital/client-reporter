# AGENTS.md

Guidance for AI coding agents working in this repository.

Client Reporter is an open-source, self-hosted, white-label client-reporting app
for web agencies. It connects the services behind a client's website, collects
data on a schedule, and renders fully branded reports (web, PDF, email, client
portal). One install serves one agency — it is single-tenant, not a multi-tenant
SaaS, and deliberately narrow in scope.

Stack: PHP 8.3+, Laravel 13, Livewire 4 (class-based components), Tailwind CSS 4
+ Vite. SQLite by default (MySQL/MariaDB and PostgreSQL supported); database
drivers for cache/session/queue (no Redis required). PDFs via `spatie/laravel-pdf`
— dompdf by default, Browsershot optional.

Core model: **Client → Sites → Integrations → collected Metrics → Reports**.

This is a working application, not a package: the app roots are `app/`, `config/`,
`database/`, `resources/`, `routes/` and `tests/`.

## Commands

- `composer check` runs the whole gate: Pint (`--test`), PHPStan, PHPUnit. Run it
  before committing.
- `composer test` / `php artisan test` — tests only. `composer pint` — auto-fix
  style. `composer stan` — PHPStan.
- After modifying PHP, run `vendor/bin/pint` (or `composer pint`) so style matches.
- Build assets with `npm run build`; `npm run dev` watches.
- Local dev is DDEV (`ddev start`, https://client-reporter.ddev.site). Prefer
  `ddev exec php artisan …` when the container must run it.
- Pass `--no-interaction` (and `--force` where destructive) to Artisan in scripts.

## Conventions

- Match the surrounding code. Before adding a file, read sibling files for the
  structure, naming and approach in that area.
- Tests are **PHPUnit, not Pest**. They live in `tests/Feature` and `tests/Unit`
  and use factories.
- Don't add a Settings/config toggle unless configurability was actually asked
  for — default to fixed, opinionated behaviour.
- Stick to the existing directory structure; don't add top-level folders or
  change dependencies without approval.
- For links to registered routes use named routes and `route()`, not hard-coded
  paths.

## PHP

- Add `declare(strict_types=1)` to every PHP file.
- Explicit return types and parameter type hints everywhere. Use constructor
  property promotion. Prefer `readonly` where it fits.
- Prefer PHPDoc blocks (with array-shape types) over inline comments.
- Let Pint remove unused imports and fix style — don't hand-format.
- **PHPStan runs at level 5 (larastan) over `app` + `tests`.** Two recurring traps:
  - It types Eloquent relation access as **non-null** (e.g. `$report->site->client`),
    so don't add `?->` where a query already guarantees the relation — PHPStan will
    flag the nullsafe as unnecessary.
  - `Model::find()` returns a union; use `Model::query()->whereKey($id)->first()`
    when you need a clean `?Model`. Add missing `@property` annotations to models
    rather than casting.

## Reports & PDFs (important)

- Client-facing report views are rendered by dompdf by default, which in this
  install **does not render `flex`, `grid`, or inline `<svg>` at all**. Report
  Blade (`resources/views/reports/**`) must use plain block/table/float layout.
- Charts and icons render as **SVG embedded as a data-URI `<img>`** — dompdf
  rasterises those through php-svg-lib, but never inline `<svg>`. See
  `App\Support\SvgChart` (line/area charts + donut gauges) and
  `App\Support\ReportIcons` / `App\Support\ProviderLogos`. In an SVG's `<text>`,
  use a single `font-family` (e.g. `sans-serif`) — php-svg-lib doesn't parse a
  comma-separated font stack and falls back to serif — and give the SVG a
  `viewBox` that matches its pixel `width`/`height` or dompdf mis-scales it.
- **Report wording is not hard-coded.** Every fixed, client-facing word or
  phrase in a report resolves through `App\Support\ReportLang::get('dotted.key')`,
  backed by the defaults in `config/report-language.php` (deep-merged over an
  agency's git-ignored `config/report-language.local.php`). When you add or change
  any client-facing report string — in `resources/views/reports/**` or a block
  class's `label()`/metric labels/auto-summary — add its key + English default to
  `config/report-language.php` and resolve it via `ReportLang`, rather than writing
  the literal in the view or class. Placeholders are `:name`. Block labels/summaries
  are frozen into the render at generation time; view chrome resolves at display.
  Number/unit formatting stays in `App\Support\Format` (not translated).
- Generating a report **freezes** a `ReportRender` snapshot (resolved block data +
  branding + range). Every client-facing surface reads the frozen render, never
  live data. Don't call `ReportGenerator::generate()` from read paths — it collects
  from external APIs and writes.

## Integrations

- An integration extends `App\Integrations\Contracts\Integration`
  (`manifest()`, `configFields()`, `verify()`, plus optional collectors/blocks).
  Bundled ones are registered in `config/client-reporter.php`.
- Scaffold new ones with `php artisan client-reporter:make-integration "Name"`.
- **Custom integrations live in the git-ignored `extensions/` directory** and are
  autoloaded + discovered automatically — never register a user's custom
  integration by editing `config/client-reporter.php` or adding classes under
  `app/` (those are core files that conflict on update). See
  `docs/creating-an-integration`.

## Frontend

- After adding a Tailwind utility class **not already used elsewhere**, run
  `npm run build` before trusting a rendered screenshot — a stale
  `public/build/` silently drops brand-new classes.
- Fonts are self-hosted via Vite; layouts must call `{{ Vite::fonts() }}` in
  `<head>`.
- Livewire's Blade compiler miscompiles a single-line inline `@if(...)…@endif`
  inside markup — use a ternary in `{{ }}` instead.

## Testing

- Cover new behaviour with feature/unit tests and factories; don't write
  throwaway tinker scripts when a test can prove it.
- Fake outbound HTTP in integration tests (`Http::fake()` / `Http::preventStrayRequests()`).
- Keep the suite green: `composer check` must pass before you finish.

## Changelog

- **Keep `CHANGELOG.md` current** — add an entry for every user-facing change in the
  same commit that makes the change. Update it frequently; don't batch it up.
- New entries go at the top of the `## Unreleased` section, one concise, past-tense
  bullet per change: **Added** / **Improved** / **Changed** / **Fixed** / **Removed**.
  Link a PR or issue when there is one, e.g. `([#123](https://github.com/coysh-digital/client-reporter/pull/123))`.
- On release, rename `## Unreleased` to `## <version> - <YYYY-MM-DD>` and open a fresh
  empty `## Unreleased` above it. Keep `config('client-reporter.version')` in step.

## Commits, pull requests & public-facing text

- **Never reference AI, assistants, or tooling in anything public.** That includes
  commit messages, PR titles and descriptions, code comments, changelog entries,
  and docs. No "Generated by…", no co-authored-by/assistant trailers, no links to
  any AI tool or session. Write everything as a human maintainer would.
- Run `composer check` before opening a PR. Branch off `main`; never commit
  straight to `main` without being asked.
- Keep client-facing report output fully white-label — it must contain no Client
  Reporter branding or references (the agency's brand only).
