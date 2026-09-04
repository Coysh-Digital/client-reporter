# Custom integrations

This directory is where your own integrations live. **Everything in here except
this README is git-ignored**, so your custom integrations are never part of the
core repository — pulling a new release of Client Reporter never touches, wipes
or overwrites them.

## How it works

Each integration is a small Composer-package-shaped folder:

```
extensions/
  my-service/
    composer.json      # PSR-4 autoload + extra.client-reporter.integrations
    src/
      MyServiceIntegration.php
      MyServiceCollector.php
```

Client Reporter automatically:

1. **Autoloads** the classes from each folder's `autoload.psr-4` map — no
   `composer require` needed.
2. **Discovers** the integration classes listed under
   `extra.client-reporter.integrations` in each folder's `composer.json`.

So installing a custom integration is just: drop the folder in here and clear
the cache (`php artisan optimize:clear`). Nothing tracked by git changes.

## Scaffold one

```bash
php artisan client-reporter:make-integration "My Service"
```

That writes a ready-to-fill skeleton into `extensions/my-service/`.

## Alternatives

- **Register a class explicitly** (e.g. one that's autoloadable another way):
  copy `config/client-reporter.local.php.example` to
  `config/client-reporter.local.php` (also git-ignored) and list the class.
- **Publish for others**: move the package to its own repository, publish it,
  and `composer require` it — Client Reporter discovers Composer-installed
  packages the same way.

See [docs/creating-an-integration](../docs/creating-an-integration/README.md)
for the full SDK guide.
