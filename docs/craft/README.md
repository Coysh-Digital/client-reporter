# Craft CMS integration

The Craft CMS integration hooks a Craft site up to Client Reporter through a small companion plugin, so you can pull CMS data into your reports.

That companion plugin lives in its own repo, [coysh-digital/client-reporter-craft](https://github.com/coysh-digital/client-reporter-craft). It hands back read-only data over HMAC-signed requests. As with all the companion connectors, Client Reporter only reads from the site — it never runs remote updates.

![Craft connector plugin](../images/craft-plugin.png)

## Installing the companion plugin

The connector is a Craft plugin, installed with Composer and enabled through Craft. On the Craft site's server:

```sh
composer require coysh-digital/client-reporter-craft
php craft plugin/install client-reporter
```

The package is `coysh-digital/client-reporter-craft` and its plugin handle is `client-reporter`. It supports Craft 4 and Craft 5 and needs PHP 8.0.2+. If you'd rather, you can install it from the **Plugin Store** in the Craft control panel and enable it under **Settings → Plugins**.

Once it's enabled, the plugin registers a small read-only site API under `/client-reporter/v1/` (for example `https://example.com/client-reporter/v1/verify`). Until you've saved a connection code, every route returns a `403` and nothing is exposed.

## Exchanging the connection code

Companion connectors authenticate with a single shared secret that Client Reporter calls the **connection code**. Client Reporter generates it; you paste it into the plugin. That same secret is what both ends use to sign and verify every request.

1. In Client Reporter, open the site, choose **Add integration → Craft CMS**, and enter the **Craft site URL** (the public URL of the site, e.g. `https://example.com`).
2. Save. Client Reporter generates a random connection code and shows it on the setup screen. (Behind the scenes it stores this code as an encrypted credential — see [Security](../security/README.md).)
3. In the Craft control panel, open **Settings → Plugins → Client Reporter Connector**, paste the connection code into the **Connection code** field, and Save. You can either paste the value directly or store it in an environment variable and reference it (the field supports Craft's environment-variable suggestions).
4. Come back to Client Reporter and press **Connect & verify**.

The connection code is a 48-character random string. Treat it like a password: anyone who has it and the site URL can read the data the connector exposes (but nothing more). You can rotate it whenever you like by re-generating it in Client Reporter and pasting the new value into the plugin.

The plugin settings also give you a **Timestamp tolerance** (default 300 seconds) — how far a request's timestamp may drift from the server clock before it's rejected. Leave it at the default unless you've got a specific reason to change it.

## Verifying the connection

When you press **Connect & verify** in Client Reporter, it makes a signed `GET` request to the plugin's `verify` endpoint. The plugin checks the signature and responds with a small identifying payload. Client Reporter confirms that the response identifies as a Craft Client Reporter connector before marking the connection as *Connected* and recording the plugin's version.

If verification fails, Client Reporter tells you why (wrong or rotated code, unreachable site, or an unexpected response). See [Troubleshooting](#troubleshooting) below.

## What the integration collects

Everything is pulled on Client Reporter's schedule; the plugin only ever responds. Here's what the Craft connector reports:

**Site status** (from the `site` endpoint)

- Craft version and PHP version
- Environment (production/staging/etc.)
- Whether a Craft core update is available
- The number of plugin updates, plus a list of each (plugin handle and available version) — reported, never applied
- A combined "updates available" total
- Queue health: pending and failed job counts
- Licence status

**Craft Commerce sales** (from the `commerce` endpoint, only when Craft Commerce is installed and enabled)

For the report's date range, across completed orders:

- Revenue and currency
- Order count and average order value
- Items sold
- Top-selling products (up to five, by revenue)

If Craft Commerce isn't installed or enabled, the connector reports that it's inactive and no store metrics show up. Craft Commerce is read entirely through this Craft connection — there's no separate integration to connect for it.

## Security model

Client Reporter always **pulls**; the plugin only ever responds, read-only. Every request is signed with HMAC-SHA256 over the request method, path, timestamp, a random nonce and a hash of the (empty) body, using the shared connection code. The plugin rejects unsigned or wrongly-signed requests, requests whose timestamp is outside the tolerance window (±300 seconds by default), and replayed nonces. The signing scheme is identical to the WordPress connector's, so a single Client Reporter client verifies against both.

For the full scheme — including how the connection code is stored encrypted at rest in Client Reporter — see [Security](../security/README.md).

## Troubleshooting

**"The website rejected the connection" / HTTP 403.** The connection code in Client Reporter doesn't match the one saved in the plugin, or no code has been saved yet. Re-copy the code from Client Reporter's setup screen into **Settings → Plugins → Client Reporter Connector** in Craft, Save, and verify again. If you stored the code in an environment variable, check that variable is set and resolved on the web server.

**"Invalid signature" or "Request timestamp out of range" errors.** Signatures are time-sensitive: the plugin rejects any request whose timestamp differs from its own clock by more than the tolerance (300 seconds by default). If the Craft server's clock is badly out of sync, fix the server time (NTP) and try again, or raise the tolerance in the plugin settings.

**"Nonce already used".** Each request carries a one-time nonce; this only shows up if a request is genuinely replayed. Just verify again — a fresh request uses a new nonce.

**"Could not reach the website".** Client Reporter couldn't connect to the site URL. Check the URL is correct and public, that the site is up, that the plugin is enabled, and that nothing is blocking the `/client-reporter/v1/` routes.

**"Not as a Craft Client Reporter connector".** The URL responded, but not with the expected connector payload — usually a wrong URL or the plugin being disabled. Confirm the plugin is enabled and that `https://<your-site>/client-reporter/v1/verify` is served by this Craft install.
