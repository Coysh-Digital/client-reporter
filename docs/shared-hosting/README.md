# Shared hosting

Client Reporter is designed to run comfortably on shared hosting. This section covers what that involves.

There is no Docker requirement and no need for Redis or a persistent worker process. By default Client Reporter uses the database cache, session and queue drivers, and a single cron entry drives all scheduled work — data collection, report generation and queued jobs — through Laravel's scheduler. PDF rendering defaults to dompdf, which needs no system binaries and is safe on shared hosts.

## The single-cron model

One cron entry operates the entire application. Add it to the crontab of the account that owns the files:

```
* * * * * cd /path/to/client-reporter && php artisan schedule:run >> /dev/null 2>&1
```

Every minute this runs the scheduler, which in turn:

- **`client-reporter:collect`** (hourly) — queues data collection for every live connection that is due. "Due" is governed by the collection interval (default 6 hours; adjustable on the admin Settings page).
- **`queue:work --stop-when-empty --max-time=55`** (every minute) — drains the database queue and then exits, so there is never a long-running process. This is what processes the queued collection jobs.
- **`client-reporter:check-updates`** (daily) — checks GitHub for a newer release and surfaces a notice to admins (if update checks are enabled).
- **`client-reporter:sync-billing`** (hourly) — syncs invoices from connected billing integrations (FreeAgent, Xero).

Because the queue worker is launched and stopped each minute, shared hosting needs nothing beyond this one cron line — no supervisor, no persistent process, no Redis.

If your host offers a cron interface (cPanel, Plesk, DirectAdmin) rather than raw crontab access, create a job that runs every minute with the command above, using the absolute path to `php` and to the project if required by your host.

## Database drivers

Shared hosts rarely offer Redis, and Client Reporter does not need it. The defaults keep everything in the database:

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

For the database itself, the default **SQLite** file (`database/database.sqlite`) needs nothing to provision. If your host provides MySQL/MariaDB, that works equally well — enter its details in the install wizard or `.env`. See [Configuration](../configuration/README.md#database).

## Document root

Point the domain's document root at the `public/` directory, never the project root:

```
/home/youraccount/client-reporter/public
```

Keeping the document root on `public/` ensures `.env`, application code, dependencies and the SQLite database stay outside the web root. If your host only lets you deploy into `public_html`, install Client Reporter alongside it and either repoint the document root or symlink `public_html` to the app's `public/` directory.

## PDF rendering with dompdf

The default **dompdf** renderer is pure PHP and needs no system binaries, so PDF export works on shared hosting out of the box. To allow logos and other remote images to appear in PDFs, keep this enabled in `.env`:

```dotenv
CLIENT_REPORTER_PDF_DRIVER=dompdf
LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=true
```

Browsershot (headless-Chromium) rendering is a VPS option and is not expected to work on typical shared hosting. See [Configuration](../configuration/README.md#pdf-rendering).

## When to move to a VPS

Shared hosting is enough for most agencies. Consider a VPS (or a managed application platform) when you want:

- **Lower collection/report latency** — a persistent `php artisan queue:work` (or Laravel Horizon with Redis) processes jobs instantly instead of once a minute.
- **Browsershot PDFs** — pixel-perfect Tailwind output requires Node.js and headless Chromium, which shared hosts generally do not provide.
- **Redis** for cache/session/queue under heavier load.
- **Shell access, larger memory limits, or long-running processes** that shared plans restrict.

On a VPS running a persistent worker, remove the `queue:work` line from `routes/console.php` so jobs are not processed twice. See [Configuration](../configuration/README.md#queue-processing).

## Common gotchas

- **PHP version.** You need PHP 8.3+. Many hosts default to an older version or a different CLI binary — check `php -v` and, if your host uses a versioned binary (e.g. `php8.3`), use that in the cron command.
- **Required extensions.** `pdo`, `mbstring`, `openssl` and `curl` must be enabled. The install wizard's requirements step checks these.
- **Writable directories.** `storage/` and `bootstrap/cache/` must be writable by the web/CLI user; the wizard prefers a writable `.env` too. Fix permissions if the requirements check fails.
- **Correct document root.** If you see raw PHP or a directory listing, the document root is almost certainly pointing at the project root instead of `public/`.
- **Cron not running.** If data never refreshes, confirm the cron entry exists, uses the right `php` binary and absolute paths, and that the account is allowed to run cron. Test by running `php artisan schedule:run` manually.
- **`APP_URL` mismatch.** Share links, emails and OAuth callbacks use `APP_URL`; set it to the real public URL.
- **Memory limits.** Very large reports or PDF generation can hit a low `memory_limit`. Raise it in your host's PHP settings if exports fail.

See also [Installation](../installation/README.md) and [Configuration](../configuration/README.md).
