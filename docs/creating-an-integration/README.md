# Creating an integration

Client Reporter can be extended with your own integrations. This section describes the Integration SDK — how an integration is structured, how it is discovered, and how to build and test one. The same SDK powers every one of the bundled integrations, so the best reference is the `app/Integrations/*` directory in the core application.

## How integrations are distributed and discovered

Integrations are plain PHP classes that extend `App\Integrations\Contracts\Integration`. The bundled ones are registered in the `integrations` array of `config/client-reporter.php`.

Third-party integrations ship as installable Composer packages. A package advertises the integration classes it provides through the `extra.client-reporter.integrations` key in its `composer.json`, and Client Reporter's `IntegrationRegistry` merges them in at boot:

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

Installing an integration is then as simple as `composer require`-ing its package.

## Scaffolding an integration

The generator scaffolds a new integration, taking the name as an argument:

```bash
php artisan client-reporter:make-integration "Matomo"
```

This writes a skeleton under `app/Integrations/<Name>/` — the integration class (manifest, config fields, `verify()`), an API client and a collector — ready for you to fill in. Register the new class in `config/client-reporter.php`.

## The shape of an Integration

An integration extends `App\Integrations\Contracts\Integration` and implements three required methods — `manifest()`, `configFields()` and `verify()` — plus optional hooks for collectors, report blocks, setup guidance and workspace-scoped connections.

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

Contract test helpers verify your integration conforms to the SDK's expectations — the manifest, configuration, authentication and collector behaviour. Extend `App\Integrations\Testing\IntegrationContractAssertions` in your test (see `tests/Feature/Integrations/IntegrationContractComplianceTest.php` for how the bundled integrations use it). Mock the provider's HTTP calls with Laravel's `Http::fake()` and assert your collector writes the metrics and snapshot you expect.

## Publishing your integration

1. Put your integration class in a Composer package.
2. Advertise it under `extra.client-reporter.integrations` in the package's `composer.json` (see above).
3. Publish the package; users install it with `composer require`, and it appears in the integrations catalog automatically.
