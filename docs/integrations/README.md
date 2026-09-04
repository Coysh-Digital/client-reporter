# Integrations

Integrations are how Client Reporter connects to the services behind your clients' websites and collects the data that reports are built from.

Each integration attaches to a **Site**, declares how it authenticates, verifies its credentials, and provides collectors that pull data in on a schedule. Every integration — the ones bundled here and any you install or build yourself — implements the same [`Integration`](https://github.com/coysh-digital/client-reporter/blob/main/app/Integrations/Contracts/Integration.php) contract, so they all connect, store credentials and collect data the same way.

## How integrations fit the data model

Client Reporter organises everything around a simple chain:

**Client → Sites → Integrations → Data → Reports**

- A **Client** is the business you report to.
- Each Client has one or more **Sites** (usually a website, plus any related property).
- Each Site has one or more **Integrations** — an analytics property here, an uptime monitor there, an ecommerce store, and so on.
- Integrations run **collectors** on a schedule that gather metrics and snapshots into Client Reporter's local **Data** store.
- **Reports** are built from that stored data using blocks, then branded and shared.

Because collected data is stored locally, historical reports stay accurate even if you later change or remove a connection. Collectors are dispatched by the `client-reporter:collect` command, which the Laravel scheduler runs every minute and drains through the queue — a single cron entry is enough on shared hosting (see [Shared hosting](../shared-hosting/README.md)).

## Adding an integration to a Site

1. Open **Integrations** from the main navigation to see the catalog, grouped by category.
2. Choose the integration you want and start its **connect** flow for the Site.
3. The setup screen renders that integration's own connection form plus a numbered **"How to connect"** guide written for that specific provider. Fill in the fields — an API key or token, and any per-site value such as a property ID or monitor.
4. Press **Connect & verify**. Client Reporter calls the provider's API to confirm the credentials work before saving the connection as *Connected*. If verification fails, the provider's error message is shown so you can correct it.

Managing integrations requires the **manage-integrations** permission.

### Authentication methods

Integrations declare one of three [`AuthMethod`](https://github.com/coysh-digital/client-reporter/blob/main/app/Integrations/Support/AuthMethod.php) values, which decides what the connect form asks for:

| Method | How it works | Used by |
| ------ | ------------ | ------- |
| **API key** | You paste an API key or token (plus any provider-specific fields such as a base URL or site ID). Stored encrypted. | Plausible, Fathom, Matomo, Umami, UptimeRobot, Uptime Kuma, Better Uptime, Mailchimp, Shopify, Stripe, WooCommerce, PageSpeed |
| **OAuth** | You click **Connect Google account** (or the provider's equivalent) and authorise through a redirect flow; Client Reporter stores the resulting refresh token. | Google Analytics 4, Google Ads, Google Search Console, FreeAgent, Xero |
| **Connector token** | Client Reporter issues a signed connection code that a companion plugin on the client's site consumes and then verifies. | WordPress, Craft CMS |

### Encrypted credential storage

Connection fields marked `secret` — API keys, tokens, OAuth refresh tokens — are kept in an encrypted credentials bag on the connection, never in plain settings, and are never sent back to the browser when you edit a connection. Non-secret values (a property ID, a base URL, a monitor selection) are stored as plain settings. See [Security](../security/README.md) for the full model.

## Workspace connections ("connect once")

Many integrations can be connected **once for the whole workspace** instead of site by site. One API key or OAuth authorisation then covers every site (or client), and Client Reporter auto-matches the provider's entities for you.

An integration opts into this by returning `true` from `supportsWorkspaceScope()`. When you connect one at the workspace level, the flow has two phases ([`WorkspaceSetup`](https://github.com/coysh-digital/client-reporter/blob/main/app/Livewire/Integrations/WorkspaceSetup.php)):

1. **Credentials** — you enter the account-level fields once (or authorise via OAuth). Client Reporter proves them by calling the provider's "list" API; any error means the credentials are wrong.
2. **Mapping** — Client Reporter lists everything the account can see (GA4 properties, Plausible sites, UptimeRobot monitors, accounting contacts…) and **auto-matches** each entity to an existing Site by URL, or — for billing integrations — to a Client by email/name. You confirm or override each match, and the per-site connections are created in bulk, all borrowing the one stored credential.

A site connection created this way only carries its own per-site value (which property, which monitor); the shared API key lives on the workspace connection and is never duplicated. What a workspace entity maps onto is set by `workspaceMapsTo()` — `site` for analytics/monitoring (the default), or `client` for billing/accounting.

Some integrations are **workspace-only** (`onlyWorkspaceScope()`), because there is no meaningful per-site connection — the accounting integrations (FreeAgent, Xero) bill the agency's clients, so they are matched to Clients rather than Sites and skip the per-site flow entirely. Integrations that are inherently per-site — a single CMS install or a single store — leave workspace scope off.

## Bundled integrations

Client Reporter ships with integrations across eight categories ([`IntegrationCategory`](https://github.com/coysh-digital/client-reporter/blob/main/app/Integrations/Support/IntegrationCategory.php)):

| Category | Integrations |
| -------- | ------------ |
| CMS | WordPress, Craft CMS |
| Analytics | Google Analytics 4, Google Ads, Plausible, Fathom, Matomo, Umami |
| Search | Google Search Console |
| Ecommerce | WooCommerce, Craft Commerce, Shopify, Stripe |
| Forms & Leads | Mailchimp |
| Monitoring | UptimeRobot, Uptime Kuma, Better Uptime |
| Performance | PageSpeed |
| Billing | FreeAgent, Xero |

The authoritative list is the `integrations` array in [`config/client-reporter.php`](https://github.com/coysh-digital/client-reporter/blob/main/config/client-reporter.php). Some integrations are *provided by* another — Craft Commerce rides on the Craft CMS connection rather than needing its own.

## Companion plugins and the read-only, HMAC-signed model

The **WordPress** and **Craft CMS** integrations connect through companion plugins installed on the client's site. Rather than storing CMS admin passwords, Client Reporter issues a signed connection token that the plugin consumes, then pulls data over **HMAC-signed requests** (`sha256`): each request is signed, timestamped and nonce-protected, and the plugin rejects anything outside the timestamp tolerance or a replayed nonce.

The connection is strictly **read-only** — Client Reporter reads data such as core/plugin versions and pending updates, but never performs updates or writes back to the client's site. The compatibility matrix in the config lets the app tell you whether a connected plugin is up to date. See:

- [WordPress](../wordpress/README.md)
- [Craft CMS](../craft/README.md)

## Per-integration guides

- [Analytics](../analytics/README.md) — Google Analytics 4, Google Ads, Plausible, Fathom, Matomo and Umami.
- [UptimeRobot](../uptime-robot/README.md) — uptime monitoring (also covers Uptime Kuma and Better Uptime).
- [WordPress](../wordpress/README.md) and [Craft CMS](../craft/README.md) — the CMS companion plugins.

## Building your own integration

Third-party integrations do **not** need to be listed in the core config. They ship as ordinary Composer packages that declare their Integration class via `extra.client-reporter.integrations`, and Client Reporter discovers them automatically at runtime. See [Creating an integration](../creating-an-integration/README.md) for the SDK and a step-by-step walkthrough.
