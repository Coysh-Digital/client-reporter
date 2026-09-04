# Configuration

This section covers configuring Client Reporter after installation.

Most configuration follows standard Laravel conventions through your `.env` file. The choices most relevant to Client Reporter are your database, the cache/session/queue drivers, and the PDF renderer. The defaults are chosen to work on shared hosting with no extra services: SQLite for the database, the database drivers for cache/session/queue, and dompdf for PDF rendering.

A few Client Reporter-specific options live in `config/client-reporter.php`, and a handful of operational settings are editable at runtime from the admin **Settings** page (they override the config defaults). Everything else is standard Laravel.

## Database

The install wizard writes your database settings, but you can also set them directly in `.env`. Client Reporter supports SQLite, MySQL/MariaDB and PostgreSQL.

### SQLite (default)

The default and simplest option — no server to provision. The database is a single file at `database/database.sqlite`:

```dotenv
DB_CONNECTION=sqlite
```

Create the file if it does not exist (`touch database/database.sqlite`), then run migrations. SQLite is well suited to shared hosting and small-to-medium agencies.

### MySQL / MariaDB

```dotenv
DB_CONNECTION=mysql        # or: mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=client_reporter
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### PostgreSQL

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=client_reporter
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

After changing the connection, run `php artisan migrate`. When switching databases on an existing install, migrate the schema into the new database first (Client Reporter does not move existing data between database engines for you).

## Cache, session and queue drivers

Out of the box these all use the **database** driver, so a fresh install needs no Redis, Memcached or extra services:

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

This is what makes the single-cron shared-hosting model work. On a VPS you can point any of these at Redis for lower latency:

```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Redis is entirely optional — the database drivers are fully supported in production.

## Queue processing

Data collection and other background work run through Laravel's queue. There are two ways to process it:

- **Scheduler-driven (default, shared-hosting friendly).** The scheduler runs `queue:work --stop-when-empty --max-time=55` every minute, draining the database queue on each tick. This needs only the single `schedule:run` cron entry and no long-running process. This is the recommended model on shared hosting.
- **Persistent worker (VPS).** Run a long-lived `php artisan queue:work` (managed by systemd/supervisor, or Laravel Horizon with Redis) for lower latency. If you do this, you can remove the `queue:work` line from `routes/console.php` so jobs are not processed twice.

See [Shared hosting](../shared-hosting/README.md) for the cron setup and [when to move to a VPS](../shared-hosting/README.md#when-to-move-to-a-vps).

## PDF rendering

Reports can be exported to PDF with one of two drivers:

| Driver | Requirements | Best for |
| --- | --- | --- |
| **dompdf** (default) | None — pure PHP | Shared hosting; zero dependencies |
| **browsershot** | Node.js + headless Chromium on the server | Pixel-perfect Tailwind fidelity on a VPS |

The default is set in `.env` and `config/client-reporter.php`:

```dotenv
CLIENT_REPORTER_PDF_DRIVER=dompdf
# Allow dompdf to load remote resources such as a logo served over http.
LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=true
```

You can also switch the driver at runtime from the admin **Settings** page (`pdf_driver`, options `dompdf` or `browsershot`). The saved setting overrides the config default, so on a VPS you can flip to Browsershot without editing files. Report views are written to render correctly under dompdf; see the [Development](../development/README.md#dompdf-safe-report-views) notes if you author your own report blocks.

## Mail

Reports and password resets are sent by email, so configure a mailer in `.env`. The default is `log` (messages are written to the log rather than sent), which is fine for evaluation but not for delivering reports:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your_user
MAIL_PASSWORD=your_password
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="reports@your-agency.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Use whatever transport your host supports (SMTP, a transactional-mail API, etc.). The from-name defaults to the application name; client-facing report emails are branded separately through the branding system.

## Client Reporter options (`config/client-reporter.php`)

A few product-specific settings live in `config/client-reporter.php`. Most have sensible defaults and are also exposed on the admin Settings page.

- **`version` / `repository`** — product identity, used by the admin UI and the GitHub update checker. Keep `version` in sync with tagged releases.
- **`integrations`** — the first-party integration classes bundled with the app. Third-party integrations are discovered automatically from installed Composer packages, so you rarely edit this.
- **`report_blocks`** — the core report blocks always available in the builder.
- **`collection.default_interval`** — default minutes between collections for a connection (360 = 6 hours).
- **`collection.retention_days`** — how long collected metrics/snapshots are kept (`null` = keep everything; recommended so historical reports stay accurate).
- **`connectors.timestamp_tolerance`** — replay-protection window (seconds) for signed requests from the WordPress/Craft companion plugins.
- **`pdf.driver`** — default PDF renderer (see above).
- **`reports.default_share_expiry_days`** — default expiry for public share links (`null` = no expiry).
- **`updates.enabled`** — whether to check GitHub for newer releases and notify admins. Client Reporter never updates itself; see [Updating](../updating/README.md).

Relevant environment variables:

```dotenv
CLIENT_REPORTER_PDF_DRIVER=dompdf
CLIENT_REPORTER_CONNECTOR_TIMESTAMP_TOLERANCE=300
CLIENT_REPORTER_UPDATE_CHECK=true
```

## Admin Settings page

Log in as an Administrator and open **Settings** (`/settings`, requires the `manage-settings` permission) to change these at runtime without touching config files. Saved values override the config defaults:

| Setting | Meaning | Range / options |
| --- | --- | --- |
| **PDF driver** (`pdf_driver`) | Which renderer produces PDF exports | `dompdf` or `browsershot` |
| **Update checks** (`updates_enabled`) | Whether to check GitHub for new releases and notify admins | on / off |
| **Collection interval** (`collection_interval`) | Minutes before a connection is considered due for collection again | 15–10080 (minutes) |
| **Retention** (`collection_retention_days`) | Days of collected metrics/snapshots to keep before pruning | 1–3650, or blank for keep-forever |
| **Share-link expiry** (`default_share_expiry_days`) | Default expiry applied to new public report share links | 1–3650 days, or blank for no default expiry |

The Settings page also shows the current version, the installation date, and the latest release information from the update checker.

## Storing integration credentials

Integration credentials (API keys, OAuth tokens, connector secrets) are stored encrypted in the database using your `APP_KEY`. Keep `APP_KEY` secret and back it up — losing it makes stored credentials unrecoverable. See [Security](../security/README.md) for details.
