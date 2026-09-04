# Shared hosting

Client Reporter is built to run happily on shared hosting. Here's what that looks like.

There's no Docker to worry about, no Redis, and no persistent worker process to keep running. By default it uses the database cache, session and queue drivers, and a single cron entry drives all the scheduled work — data collection, report generation and queued jobs — through Laravel's scheduler. PDF rendering defaults to dompdf, which needs no system binaries and is safe on shared hosts.

## The single-cron model

One cron entry runs the whole application. Add it to the crontab of the account that owns the files:

```
* * * * * cd /path/to/client-reporter && php artisan schedule:run >> /dev/null 2>&1
```

Every minute this runs the scheduler, which in turn:

- **`client-reporter:collect`** (hourly) — queues data collection for every live connection that is due. "Due" is governed by the collection interval (default 6 hours; adjustable on the admin Settings page).
- **`queue:work --stop-when-empty --max-time=55`** (every minute) — drains the database queue and then exits, so there is never a long-running process. This is what processes the queued collection jobs.
- **`client-reporter:check-updates`** (daily) — checks GitHub for a newer release and surfaces a notice to admins (if update checks are enabled).
- **`client-reporter:sync-billing`** (hourly) — syncs invoices from connected billing integrations (FreeAgent, Xero).

Because the queue worker is started and stopped again each minute, shared hosting needs nothing more than this one cron line — no supervisor, no persistent process, no Redis.

If your host gives you a cron interface (cPanel, Plesk, DirectAdmin) instead of raw crontab access, just create a job that runs every minute with the command above, using the absolute path to `php` and to the project if your host needs it.

## Database drivers

Shared hosts rarely offer Redis, and that's fine — Client Reporter doesn't need it. The defaults keep everything in the database:

```dotenv
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

For the database itself, the default **SQLite** file (`database/database.sqlite`) needs nothing set up at all. If your host gives you MySQL/MariaDB, that works just as well — pop the details into the install wizard or `.env`. See [Configuration](../configuration/README.md#database).

## Document root

Point the domain's document root at the `public/` directory, never the project root itself:

```
/home/youraccount/client-reporter/public
```

Keeping the document root on `public/` is what keeps your `.env`, your code, your dependencies and the SQLite database out of the web root. If your host only lets you deploy into `public_html`, install Client Reporter alongside it and either repoint the document root or symlink `public_html` to the app's `public/` directory.

## PDF rendering with dompdf

The default **dompdf** renderer is pure PHP and needs no system binaries, so PDF export just works on shared hosting out of the box. To let logos and other remote images show up in your PDFs, keep this enabled in `.env`:

```dotenv
CLIENT_REPORTER_PDF_DRIVER=dompdf
LARAVEL_PDF_DOMPDF_REMOTE_ENABLED=true
```

Browsershot (headless-Chromium) rendering is really a VPS thing and won't work on typical shared hosting, so don't count on it there. See [Configuration](../configuration/README.md#pdf-rendering).

## When to move to a VPS

Shared hosting is plenty for most agencies. But it might be worth moving to a VPS (or a managed application platform) when you want:

- **Lower collection/report latency** — a persistent `php artisan queue:work` (or Laravel Horizon with Redis) processes jobs instantly instead of once a minute.
- **Browsershot PDFs** — pixel-perfect Tailwind output requires Node.js and headless Chromium, which shared hosts generally do not provide.
- **Redis** for cache/session/queue under heavier load.
- **Shell access, larger memory limits, or long-running processes** that shared plans restrict.

If you're on a VPS running a persistent worker, remove the `queue:work` line from `routes/console.php` so jobs aren't processed twice. See [Configuration](../configuration/README.md#queue-processing).

## Common gotchas

- **PHP version.** You need PHP 8.3+. Lots of hosts default to an older version or a different CLI binary — check `php -v`, and if your host uses a versioned binary (e.g. `php8.3`), use that one in the cron command.
- **Required extensions.** `pdo`, `mbstring`, `openssl` and `curl` all need to be enabled. The install wizard's requirements step checks these for you.
- **Writable directories.** `storage/` and `bootstrap/cache/` have to be writable by the web/CLI user, and the wizard likes a writable `.env` too. If the requirements check fails, fix the permissions.
- **Correct document root.** If you're seeing raw PHP or a directory listing, the document root is almost certainly pointing at the project root instead of `public/`.
- **Cron not running.** If data never refreshes, check the cron entry actually exists, uses the right `php` binary and absolute paths, and that the account is allowed to run cron. Test it by running `php artisan schedule:run` by hand.
- **`APP_URL` mismatch.** Share links, emails and OAuth callbacks all use `APP_URL`, so set it to the real public URL.
- **Memory limits.** Very large reports or PDF generation can bump into a low `memory_limit`. If exports fail, raise it in your host's PHP settings.

See also [Installation](../installation/README.md) and [Configuration](../configuration/README.md).
