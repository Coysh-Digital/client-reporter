# Installation

This page gets you from nothing to logged in and adding your first client.

Client Reporter is a standard Laravel 13 app, so if you've deployed Laravel before none of this will surprise you. The short version: clone the repo, install the dependencies, build the front-end assets, point your web server at the `public/` directory, then open the site in a browser and let the install wizard do the rest. There's no Docker to set up, and because it defaults to SQLite there's nothing extra to provision to get going.

## Requirements

| Requirement | Notes |
| --- | --- |
| **PHP 8.3+** | With the `pdo`, `mbstring`, `openssl` and `curl` extensions enabled. The wizard checks these for you. |
| **Composer 2** | To install PHP dependencies. |
| **Node.js 18+ and npm** | To build the front-end assets (Vite + Tailwind CSS 4). Only needed at build time, not at runtime. |
| **A database** | SQLite (default, zero setup), MySQL 8 / MariaDB 10.3+, or PostgreSQL. See [Configuration](../configuration/README.md). |
| **A web server** | Anything that can serve a PHP app — Apache, nginx, Caddy, or a shared-hosting control panel. The document root must point at `public/`. |
| **Writable `storage/`** | Laravel needs `storage/` and `bootstrap/cache/` writable. The wizard also prefers a writable `.env`. |

If your host runs headless Chromium and you want pixel-perfect PDFs, you can switch to the Browsershot renderer later — but you don't have to. The default dompdf renderer needs no extra binaries at all. See [Configuration](../configuration/README.md#pdf-rendering).

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

At the very least, set `APP_URL` in `.env` to the URL you'll serve the app from. You don't need to touch the database settings by hand — the install wizard writes those for you. And if you'd rather do the whole thing from the command line, `composer setup` runs `composer install`, copies `.env`, generates the key, runs migrations and builds assets in one go.

## 2. Point your web root at `public/`

Like any Laravel app, the web server's document root has to be the `public/` directory, never the project root. That's what keeps your `.env`, your code and your dependencies out of the web root where nobody can reach them.

- **Shared hosting:** point the domain's document root at `.../client-reporter/public`. See [Shared hosting](../shared-hosting/README.md) for the full walk-through.
- **nginx / Apache:** set `root` (nginx) or `DocumentRoot` (Apache) to the `public/` path and enable the standard Laravel rewrite rules.
- **Local development:** `php artisan serve` serves `public/` for you on `http://localhost:8000`.

## 3. Run the browser install wizard

Open the site in your browser. If the app isn't installed yet, you'll land on `/install`, which is a four-step wizard (`App\Livewire\Install\Wizard`):

![The install wizard](../images/install-wizard.png)

1. **Requirements check.** Checks for PHP 8.3+, the extensions you need (`pdo`, `mbstring`, `openssl`, `curl`), that `storage/` is writable, and whether `.env` is writable. The required checks all have to pass before you can move on. The `.env` check is just advisory — if `.env` isn't writable, don't worry, the wizard shows you the values to paste in yourself at the final step.
2. **Database.** Pick SQLite (the default — nothing else to fill in), MySQL, or PostgreSQL. For MySQL/PostgreSQL you enter host, port, database name, username and password, and the wizard tests the connection before it lets you carry on. Your credentials are never echoed back in error messages.
3. **Administrator account.** Enter the name, email and password (at least 8 characters, confirmed) for your first Administrator user.
4. **Agency details and finish.** Enter your agency name, the application URL and a primary brand colour. Hit **Install** and it:
   - writes the database and `APP_URL` settings to `.env` (or shows them for manual copying if `.env` is not writable),
   - runs `php artisan migrate --force`,
   - creates the Administrator account,
   - creates the global branding profile from the agency name and primary colour,
   - records the installation as complete, and
   - clears caches and redirects you to the login page.

Now log in with the Administrator account you just created.

## 4. Set up the scheduler cron entry

All the background work — data collection, queued jobs, the daily update check, billing sync — runs through Laravel's scheduler, and that's driven by a single cron entry. Add this to the crontab of the user that owns the files:

```
* * * * * cd /path/to/client-reporter && php artisan schedule:run >> /dev/null 2>&1
```

That single entry is all you need on shared hosting: the scheduler queues due data collections hourly and drains the database queue every minute, so there's no persistent worker process to keep alive. On a VPS you can instead run a persistent `php artisan queue:work` (or Horizon) if you want — see [Shared hosting](../shared-hosting/README.md) and [Configuration](../configuration/README.md#queue-processing).

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
- [Updating](../updating/README.md) — keeping your install up to date.
- [Security](../security/README.md) — how your credentials and share links are kept safe.
