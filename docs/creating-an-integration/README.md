# Creating an integration

Client Reporter can be extended with your own integrations. This section describes the Integration SDK — how an integration is structured, how it is discovered, and how to scaffold one.

> **Note on status:** The shapes described here are the intended design of the Integration SDK. Where an exact API is not yet finalised, treat it as the planned contract rather than a stable signature — parts are still **in progress**.

## How integrations are distributed and discovered

Third-party integrations are installable Composer packages. A package advertises the integrations it provides through the `extra.client-reporter.integrations` key in its `composer.json`, and Client Reporter discovers them from there. This means installing an integration is as simple as requiring its Composer package.

## Scaffolding an integration

A generator command scaffolds a new integration for you:

```bash
php artisan client-reporter:make-integration
```

This creates the skeleton of an integration — its manifest, config fields, authentication method, collectors, metrics and report blocks — ready for you to fill in.

## The shape of an Integration

An Integration declares:

- **Manifest** — identity and metadata: name, category (CMS, Analytics, Ecommerce, Monitoring), and how it presents itself in the UI.
- **Config fields** — the settings a user provides when attaching the integration to a Site (for example an API key, a property ID or a site URL).
- **Authentication** — the authentication method the integration uses (for example an API key/token, OAuth, or HMAC-signed requests for a companion connector).
- **Collectors** — the units that fetch data from the service on a schedule and persist it as collected Data.
- **Metrics** — the values derived from collected data that reports can draw on.
- **Report blocks** — the presentational blocks that render metrics into a client report.

## Testing your integration

Contract test helpers are available so you can verify your integration conforms to the SDK's expectations — covering the manifest, configuration, authentication and collector behaviour. Extend `App\Integrations\Testing\IntegrationContractAssertions` in your test (see `tests/Feature/Integrations/IntegrationContractComplianceTest.php` for how the bundled integrations use it).

Topics this section will cover:

- A full walkthrough of building an integration end to end — coming soon
- Reference for each part of the manifest and config fields — coming soon
- Implementing authentication methods — coming soon
- Writing collectors, metrics and report blocks — coming soon
- Publishing your integration as a Composer package with `extra.client-reporter.integrations`
- Using the contract test helpers (`App\Integrations\Testing\IntegrationContractAssertions`)
