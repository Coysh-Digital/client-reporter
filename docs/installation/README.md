# Installation

This section covers installing Client Reporter and getting to the point where you can log in and start adding clients.

Client Reporter is a standard Laravel 13 application. The quickest path is to clone the repository, install dependencies, build the front-end assets, point your web server at the `public/` directory and open the site in a browser to run the install wizard. No Docker is required, and the default SQLite database means there is nothing extra to provision to get started.

## Requirements

| Requirement | Notes |
| --- | --- |
| **PHP 8.3+** | With the `pdo`, `mbstring`, `openssl` and `curl` extensions enabled. The wizard checks these for you. |
| **Composer 2** | To install PHP dependencies. |
| **Node.js 18+ and npm** | To build the front-end assets (Vite + Tailwind CSS 4). Only needed at build time, not at runtime. |
| **A database** | SQLite (default, zero setup), MySQL 8 / MariaDB 10.3+, or PostgreSQL. See [Configuration](../configuration/README.md). |
| **A web server** | Anything that can serve a PHP app — Apache, nginx, Caddy, or a shared-hosting control panel. The document root must point at `public/`. |
| **Writable `storage/`** | Laravel needs `storage/` and `bootstrap/cache/` writable. The wizard also prefers a writable `.env`. |

If your host runs headless Chromium and you want pixel-perfect PDFs you can optionally switch to the Browsershot renderer later; the default dompdf renderer needs no extra binaries. See [Configuration](../configuration/README.md#pdf-rendering).

## 1. Clone and install dependencies

```bash
git clone https://github.com/coysh-digital/client-reporter.git
cd client-reporter
composer install
npm install && npm run build
```

Create your environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

At minimum, set `APP_URL` in `.env` to the URL the app will be served from. You do **not** need to configure the database by hand — the install wizard writes those values for you. If you prefer to do everything from the command line, `composer setup` runs `composer install`, copies `.env`, generates the key, runs migrations and builds assets in one step.

## 2. Point your web root at `public/`

As with any Laravel application, the web server's document root must be the `public/` directory, never the project root. This keeps `.env`, application code and dependencies out of the web root.

- **Shared hosting:** point the domain's document root at `.../client-reporter/public`. See [Shared hosting](../shared-hosting/README.md) for the full walk-through.
- **nginx / Apache:** set `root` (nginx) or `DocumentRoot` (Apache) to the `public/` path and enable the standard Laravel rewrite rules.
- **Local development:** `php artisan serve` serves `public/` for you on `http://localhost:8000`.

## 3. Run the browser install wizard

Open the site in your browser. If the app is not yet installed you are taken to `/install`, a four-step wizard (`App\Livewire\Install\Wizard`):

1. **Requirements check.** Verifies PHP 8.3+, the required extensions (`pdo`, `mbstring`, `openssl`, `curl`), that `storage/` is writable, and whether `.env` is writable. Required checks must pass before you can continue. The `.env` check is advisory — if `.env` is not writable the wizard shows you the values to paste in yourself at the final step.
2. **Database.** Choose SQLite (default — no further details needed), MySQL, or PostgreSQL. For MySQL/PostgreSQL you enter host, port, database name, username and password, and the wizard tests the connection before letting you proceed. Credentials are never echoed back in error messages.
3. **Administrator account.** Enter the name, email and password (minimum 8 characters, confirmed) for the first Administrator user.
4. **Agency details and finish.** Enter your agency name, the application URL and a primary brand colour. Clicking **Install** then:
   - writes the database and `APP_URL` settings to `.env` (or shows them for manual copying if `.env` is not writable),
   - runs `php artisan migrate --force`,
   - creates the Administrator account,
   - creates the global branding profile from the agency name and primary colour,
   - records the installation as complete, and
   - clears caches and redirects you to the login page.

Log in with the Administrator account you just created.

## 4. Set up the scheduler cron entry

Client Reporter does all of its background work — data collection, queued jobs, the daily update check and billing sync — through Laravel's scheduler, driven by a single cron entry. Add this to the crontab of the user that owns the files:

```
* * * * * cd /path/to/client-reporter && php artisan schedule:run >> /dev/null 2>&1
```

That one entry is enough on shared hosting: the scheduler queues due data collections hourly and drains the database queue every minute, so no persistent worker process is required. On a VPS you may instead run a persistent `php artisan queue:work` (or Horizon) — see [Shared hosting](../shared-hosting/README.md) and [Configuration](../configuration/README.md#queue-processing).

## Post-install checklist

- [ ] You can log in as the Administrator.
- [ ] The scheduler cron entry is installed and running (`php artisan schedule:run` runs without error).
- [ ] `APP_URL` matches the URL you actually serve from (needed for share links, emails and OAuth callbacks).
- [ ] Mail is configured if you plan to email reports or use password resets — see [Configuration](../configuration/README.md#mail).
- [ ] Review the admin **Settings** page (PDF driver, update checks, collection interval, retention, share-link expiry) — see [Configuration](../configuration/README.md#admin-settings-page).
- [ ] Add your first client, site and integration, then generate a report. See [Configuration](../configuration/README.md) and the [Integrations](../integrations/README.md) docs.

## Where to next

- [Configuration](../configuration/README.md) — databases, drivers, mail, PDF rendering and the admin settings.
- [Shared hosting](../shared-hosting/README.md) — running comfortably on a single cron entry.
- [Updating](../updating/README.md) — keeping an installation up to date.
- [Security](../security/README.md) — how credentials and share links are protected.
