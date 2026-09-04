# Development

This section is for developers working on Client Reporter itself.

Client Reporter is a Laravel 13 application using Livewire 4, Tailwind CSS 4 and PHP 8.3+. It follows standard Laravel conventions, with Laravel Pint for code style, Larastan/PHPStan at level 5 for static analysis, and PHPUnit for tests. See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the full contributor guide, including how to run `composer check`.

Topics this section will cover:

- Setting up a local development environment — see [CONTRIBUTING.md](../../CONTRIBUTING.md)
- The core domain model: Client → Sites → Integrations → Data → Reports
- Staff roles (Administrator, Manager, Viewer) and the Client portal role
- Coding standards: Pint, PHPStan level 5, PHPUnit
- Running the check suite: `composer check`, `php artisan test`, `./vendor/bin/pint`, `./vendor/bin/phpstan analyse --memory-limit=512M`
- Project structure and conventions — coming soon
- Building an integration — see [Creating an integration](../creating-an-integration/README.md)
