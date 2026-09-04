# Contributing to Client Reporter

Thank you for your interest in contributing to Client Reporter. This guide covers how to set up a development environment, the coding standards we follow, and the pull request process.

By participating in this project you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## Development setup

1. Fork and clone the repository:

   ```bash
   git clone https://github.com/coysh-digital/client-reporter.git
   cd client-reporter
   ```

2. Install PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create your environment file and generate an application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. The default SQLite configuration is the easiest way to get started. Run the migrations and build the front-end assets:

   ```bash
   php artisan migrate
   npm run build
   ```

You can serve the app locally with `php artisan serve` and run the Vite dev server with `npm run dev`.

## Coding standards

- **Laravel Pint** enforces our code style. Run it before committing.
- **Larastan / PHPStan at level 5** must pass with no new errors.
- **PHPUnit** is used for tests. New behaviour must be covered by tests.

We aim to match the conventions of the surrounding Laravel code — favour clear, readable code over cleverness.

## Running checks

Run the full check suite (Pint, PHPStan and the test suite) with:

```bash
composer check
```

You can also run the individual tools:

```bash
# Run the test suite
php artisan test

# Check (and fix) code style
./vendor/bin/pint          # fix
./vendor/bin/pint --test   # check only, as CI runs it

# Static analysis
./vendor/bin/phpstan analyse --memory-limit=512M
```

Please make sure `composer check` passes before opening a pull request.

## Branch and pull request flow

1. Create a topic branch off the default branch (for example `feature/plausible-metrics` or `fix/report-token-expiry`).
2. Make your changes in focused commits.
3. Add or update tests for any new or changed behaviour — **tests are required for new behaviour.**
4. Ensure `composer check` passes locally.
5. Update the relevant documentation under `docs/` and the `CHANGELOG.md` where appropriate.
6. Open a pull request against the default branch and fill in the pull request template.

A maintainer will review your pull request. Please be responsive to review feedback so we can get your contribution merged.

## Reporting bugs and requesting features

Use the GitHub issue templates:

- **Bug report** — for something that is broken.
- **Feature request** — for a new capability that fits the project's scope.
- **Integration proposal** — to propose a new service integration.

Please check the [README](README.md#what-client-reporter-deliberately-does-not-do) for what is deliberately out of scope before opening a feature request.
