# Updating

Here's how to keep an existing Client Reporter install up to date.

Since Client Reporter is source you clone, updating just means pulling the latest code and running the usual Laravel update steps — installing any new dependencies, running database migrations and rebuilding the front-end assets. It never updates itself, so you're always in control of when and how you upgrade.

## Before you update

- **Back up your database.** For SQLite, copy `database/database.sqlite`; for MySQL/PostgreSQL, take a dump. Migrations only go forward, so there's no undo.
- **Back up `.env`** — and keep `APP_KEY` safe in particular, since it's what decrypts your stored integration credentials.
- **Read the [changelog](../../CHANGELOG.md)** for the release you're moving to, and take note of anything in a **BREAKING** or upgrade-notes section.
- It's worth flipping on maintenance mode while you upgrade: `php artisan down` (then `php artisan up` when you're done).

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

`client-reporter:update` is a little convenience command that wraps up the last of the upgrade: it runs `php artisan migrate --force` and then `php artisan optimize:clear`. It does **not** download or replace your application code — always pull with git/composer first, then run it. Pass `--force` to skip the confirmation prompt (handy in scripts):

```bash
php artisan client-reporter:update --force
```

If you'd rather run the steps yourself instead of using the helper:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Once you're done, reload the app. The admin **Settings** page shows the running version, so you can confirm the upgrade actually took.

## Updating on shared hosting without shell access

If you can't run git/composer/npm on the server, prepare the release on a machine that can and then deploy the built files:

1. On a local machine, pull the new code and run `composer install --no-dev --optimize-autoloader` and `npm install && npm run build`.
2. Upload the updated files (including `vendor/` and the built `public/build/` assets) to the server, keeping your `.env` and `database/database.sqlite` in place.
3. Run the migrations and cache clear. If your host gives you a cron or terminal feature, run:
   ```
   php artisan client-reporter:update --force
   ```
   If you've got no way to run artisan at all, the next scheduled `schedule:run` will keep the app ticking over — but sort out running the migrations somehow before you rely on any new features, because pending migrations won't apply themselves.

## Caches

`php artisan optimize:clear` (which `client-reporter:update` runs for you) clears the config, route, view and application caches. If you cache config/routes for performance in production, rebuild them afterwards:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## The in-app update checker

Client Reporter can give you a nudge when a newer release is out, but it never installs anything itself. When it's enabled, `client-reporter:check-updates` runs daily (via the scheduler) and compares your installed `version` against the latest GitHub release. If there's a newer stable release, Administrators see a notice, and the current/latest versions show up on the admin **Settings** page.

Toggle this from the Settings page (**Update checks**) or in configuration:

```dotenv
CLIENT_REPORTER_UPDATE_CHECK=true
```

The config for this lives under `updates.*` in `config/client-reporter.php` (endpoint and check interval). See [Configuration](../configuration/README.md#client-reporter-options-configclient-reporterphp).

## Following releases

- Watch or star the repo at [github.com/coysh-digital/client-reporter](https://github.com/coysh-digital/client-reporter) to get release notifications.
- Read the [CHANGELOG.md](../../CHANGELOG.md) — it follows [Keep a Changelog](https://keepachangelog.com/) and [Semantic Versioning](https://semver.org/), so breaking changes are always called out.
- Update the companion WordPress and Craft plugins when a release mentions new minimum versions; the app tells you about plugin compatibility.

See also [Installation](../installation/README.md) and [Shared hosting](../shared-hosting/README.md).
