# Updating

This section covers keeping an existing Client Reporter installation up to date.

Because Client Reporter is distributed as source you clone, updating means pulling the latest code and running the usual Laravel update steps — installing any new dependencies, running database migrations and rebuilding the front-end assets. Client Reporter never updates itself; you stay in control of when and how you upgrade.

## Before you update

- **Back up your database.** For SQLite copy `database/database.sqlite`; for MySQL/PostgreSQL take a dump. Migrations are forward-only.
- **Back up `.env`** (and in particular keep `APP_KEY` safe — it decrypts stored integration credentials).
- **Read the [changelog](../../CHANGELOG.md)** for the release you are moving to, and note anything in a **BREAKING** or upgrade-notes section.
- Consider enabling maintenance mode during the upgrade: `php artisan down` (and `php artisan up` when finished).

## Standard update (shell access)

From the project root:

```bash
# 1. Pull the latest code (or check out a release tag)
git pull            # or: git fetch --tags && git checkout v0.1.0

# 2. Install PHP dependencies without dev tooling
composer install --no-dev --optimize-autoloader

# 3. Rebuild front-end assets
npm install && npm run build

# 4. Run database migrations and clear caches
php artisan client-reporter:update
```

`client-reporter:update` is a convenience command that finishes the upgrade: it runs `php artisan migrate --force` and then `php artisan optimize:clear`. It does **not** download or replace application code — always pull with git/composer first, then run it. Pass `--force` to skip the confirmation prompt (useful in scripts):

```bash
php artisan client-reporter:update --force
```

If you prefer to run the steps yourself instead of the helper:

```bash
php artisan migrate --force
php artisan optimize:clear
```

After updating, reload the application. The admin **Settings** page shows the running version so you can confirm the upgrade took effect.

## Updating on shared hosting without shell access

If you cannot run git/composer/npm on the server, prepare the release on a machine that can, then deploy the built files:

1. On a local machine, pull the new code and run `composer install --no-dev --optimize-autoloader` and `npm install && npm run build`.
2. Upload the updated files (including `vendor/` and the built `public/build/` assets) to the server, preserving your `.env` and `database/database.sqlite`.
3. Run the migrations and cache clear. If your host exposes a cron or terminal feature, run:
   ```
   php artisan client-reporter:update --force
   ```
   If you have no way to run artisan at all, the next scheduled `schedule:run` will keep the app working, but you should arrange to run migrations somehow before relying on new features — pending migrations will not apply themselves.

## Caches

`php artisan optimize:clear` (run for you by `client-reporter:update`) clears the config, route, view and application caches. If you cache config/routes for performance in production, rebuild them afterwards:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## The in-app update checker

Client Reporter can notify you when a newer release is available, but it never installs anything itself. When enabled, `client-reporter:check-updates` runs daily (via the scheduler) and compares your installed `version` against the latest GitHub release. If a newer stable release exists, Administrators see a notice, and the current/latest versions appear on the admin **Settings** page.

Toggle this from the Settings page (**Update checks**) or in configuration:

```dotenv
CLIENT_REPORTER_UPDATE_CHECK=true
```

The relevant config lives under `updates.*` in `config/client-reporter.php` (endpoint and check interval). See [Configuration](../configuration/README.md#client-reporter-options-configclient-reporterphp).

## Following releases

- Watch or star the repository at [github.com/coysh-digital/client-reporter](https://github.com/coysh-digital/client-reporter) for release notifications.
- Read the [CHANGELOG.md](../../CHANGELOG.md) — it follows [Keep a Changelog](https://keepachangelog.com/) and [Semantic Versioning](https://semver.org/), so breaking changes are called out.
- Update the companion WordPress and Craft plugins when a release notes new minimum versions; the app reports plugin compatibility to you.

See also [Installation](../installation/README.md) and [Shared hosting](../shared-hosting/README.md).
