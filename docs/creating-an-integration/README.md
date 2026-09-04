# Creating an integration

Missing a service you use? You can add it yourself. This is the guide to the Integration SDK — how an integration hangs together, how Client Reporter finds it, and how to build and test one. Every bundled integration uses this exact same SDK, so honestly the best reference of all is the `app/Integrations/*` folder in the core app — copy whichever one is closest to what you're building.

## How integrations are distributed and discovered

An integration is just a plain PHP class that extends `App\Integrations\Contracts\Integration`. The ones that ship with Client Reporter are listed in the `integrations` array of `config/client-reporter.php`.

Your own integrations can ship as Composer packages. A package points at the integration classes it provides with the `extra.client-reporter.integrations` key in its `composer.json`, and Client Reporter's `IntegrationRegistry` picks them up at boot:

```json
{
    "extra": {
        "client-reporter": {
            "integrations": [
                "Acme\\ClientReporter\\FathomIntegration"
            ]
        }
    }
}
```

Then anyone can install it with a plain `composer require` — no marketplace, no registration, nothing to approve.

For your **own** integrations you don't even need Composer: anything you drop into the git-ignored `extensions/` directory is autoloaded and discovered automatically (see [Keeping custom integrations across updates](#keeping-custom-integrations-across-updates) below).

## Scaffolding an integration

Don't start from a blank file — let the generator write the skeleton for you. Just give it a name:

```bash
php artisan client-reporter:make-integration "Matomo"
```

That drops a ready-to-fill package into `extensions/matomo/` — its own `composer.json` (with a PSR-4 autoload map and an `extra.client-reporter.integrations` entry), the integration class (manifest, config fields, `verify()`) and a collector. Because it lives in `extensions/`, Client Reporter **autoloads and discovers it automatically** — there's nothing to register and no `composer require` to run. Fill it in, run `php artisan optimize:clear`, and it appears in the integrations catalog.

## The shape of an integration

There are only three methods you *have* to write — `manifest()`, `configFields()` and `verify()` — and then a handful of optional hooks (collectors, report blocks, setup steps, workspace connections) you reach for when you need them. Here's each piece.

### Manifest

`manifest()` returns an `IntegrationManifest` describing the integration's identity:

```php
public function manifest(): IntegrationManifest
{
    return new IntegrationManifest(
        key: 'plausible',                       // unique, stable, snake_case
        name: 'Plausible',
        category: IntegrationCategory::Analytics,
        authMethod: AuthMethod::ApiKey,
        description: 'Privacy-friendly visitor analytics.',
        icon: 'vendor/logos/plausible.svg',     // optional, relative to public/
        version: '1.0.0',
    );
}
```

`IntegrationCategory` values are: `Cms`, `Analytics`, `Search`, `Ecommerce`, `Forms`, `Monitoring`, `Performance` and `Billing`.

### Config fields

`configFields()` returns the settings a user provides when connecting. Each is a `ConfigField`; use the `ConfigField::apiKey(...)` helper for secret credentials and the constructor for everything else. `scope: 'site'` marks a field as per-site (property IDs, site domains) so it is excluded from workspace-level forms.

```php
public function configFields(): array
{
    return [
        ConfigField::apiKey('api_token', 'API key', 'A Stats API key from Plausible.'),
        new ConfigField(key: 'site_id', label: 'Site ID (domain)', required: true, scope: 'site'),
        new ConfigField(key: 'base_url', label: 'Plausible URL', required: false,
            help: 'Only for self-hosted Plausible.'),
    ];
}
```

Secret fields are stored encrypted at rest (see [Security](../security/README.md)). Read them back in collectors and `verify()` with `$connection->credential('api_token')` and `$connection->setting('site_id')`.

### Authentication and `verify()`

The `AuthMethod` enum has three cases: `ApiKey`, `OAuth` and `ConnectorToken` (the HMAC-signed companion-plugin model used by WordPress and Craft). `verify()` is called when a user connects an integration; make a lightweight real request and return a `VerificationResult`:

```php
public function verify(SiteIntegration $connection): VerificationResult
{
    try {
        (new PlausibleClient(
            (string) $connection->credential('api_token'),
            (string) $connection->setting('site_id'),
        ))->aggregate(DateRange::last7Days(), ['visitors']);
    } catch (IntegrationException $e) {
        return VerificationResult::failure($e->getMessage());
    }

    return VerificationResult::success('Connected to Plausible.');
}
```

### Setup guidance

Override `setupSteps()` to render a numbered "How to connect" card on the setup screen (HTML is allowed). Every bundled integration provides these; a test enforces it.

```php
public function setupSteps(): array
{
    return [
        'In Plausible, open <strong>Settings → API keys</strong>.',
        'Create a key and copy it.',
        'Enter the key and your domain below, then <strong>Connect &amp; verify</strong>.',
    ];
}
```

### Collectors

`collectors()` returns the units that fetch data on a schedule and persist it as metrics and snapshots. A collector implements `App\Integrations\Contracts\Collector`, declares a key and interval, and writes `metric` values plus an optional `snapshot` (structured JSON for tables like top pages). Mirror an existing collector — `app/Integrations/Plausible/PlausibleCollector.php` is a good template. Because the report blocks are category-based, emitting the standard `analytics.*` metrics and a matching snapshot means your provider renders in the generic analytics blocks with **no new block code**.

### Report blocks

`reportBlocks()` returns any bespoke block types the integration adds. Most integrations return `[]` and rely on the shared, category-based blocks already registered in `config/client-reporter.php` (`report_blocks`). Only add a block when you need a presentation the shared blocks don't cover. See [Reports](../reports/README.md) for the block model.

### Workspace-scoped connections (optional)

If one credential naturally covers many sites (an account-wide API key or a single OAuth login), opt into "connect once for the whole workspace":

- `supportsWorkspaceScope(): bool` — return `true`.
- `discoverConnections(WorkspaceIntegration $workspace): array` — list the entities the account exposes (as `DiscoveredConnection` DTOs) so Client Reporter can auto-match them to sites (by URL) or clients (by email/name).
- `workspaceMapsTo(): string` — `'site'` (default) or `'client'` (billing integrations).
- `onlyWorkspaceScope(): bool` — return `true` if no per-site connection exists at all.

See `WorkspaceSetup` and the analytics/billing integrations for the full pattern.

## Testing your integration

You don't have to write the boilerplate checks yourself — there are contract test helpers that make sure your integration behaves the way the SDK expects (the manifest, config, auth and collector all line up). Extend `App\Integrations\Testing\IntegrationContractAssertions` in your test, and peek at `tests/Feature/Integrations/IntegrationContractComplianceTest.php` to see how the bundled ones do it. For the data side, fake the provider's HTTP calls with Laravel's `Http::fake()` and assert your collector writes the metrics and snapshot you expect — no real network needed.

## Keeping custom integrations across updates

Your integrations should never be affected when you update Client Reporter, because they live **outside the core repository**:

- The **`extensions/` directory is git-ignored** (only its README is tracked). Anything you put there — including whatever `make-integration` scaffolds — is invisible to git, so `git pull`, a fresh release, or even `git clean` never touches it.
- Client Reporter **autoloads** each `extensions/<pkg>/` package from its own `autoload.psr-4` map and **discovers** the classes in its `extra.client-reporter.integrations` — with no `composer require` and no edit to any tracked file (`config/client-reporter.php` and `composer.json` stay untouched).

So the update-safe recipe is simply: keep custom integrations in `extensions/`. To update Client Reporter, pull the new release and run `php artisan client-reporter:update` — your `extensions/` folder is left exactly as it was.

Two variations, both still update-safe:

- **Register a class explicitly** (for one that's autoloadable some other way): copy `config/client-reporter.local.php.example` to `config/client-reporter.local.php` (also git-ignored) and list the class under `integrations`.
- **Install a published package** with `composer require`. This is the one case that edits tracked files (`composer.json`/`composer.lock`); those changes are additive and normally merge cleanly on update — just re-run `composer install` afterwards.

Avoid adding a custom integration by editing `config/client-reporter.php` or dropping classes into `app/` — those are core files, and your changes there can conflict on update.

## Publishing your integration

Once it works, sharing it is easy:

1. Drop your integration class into a Composer package.
2. Point at it with `extra.client-reporter.integrations` in that package's `composer.json` (see above).
3. Publish the package. Anyone who `composer require`s it gets your integration in their catalog automatically — that's the whole distribution story.

If you build something useful, I'd love to hear about it.
