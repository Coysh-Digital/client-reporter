# WordPress integration

The WordPress integration hooks a WordPress site up to Client Reporter through a small companion plugin, so you can pull CMS data into your reports.

That companion plugin lives in its own repo, [coysh-digital/client-reporter-wordpress](https://github.com/coysh-digital/client-reporter-wordpress). It hands back read-only data over HMAC-signed requests, and that's all it does. Client Reporter only ever reads from the site — it never runs updates, installs plugins, or changes anything.

![WordPress connector plugin](../images/wordpress-plugin.png)

## Installing the companion plugin

The connector is a normal WordPress plugin, so install it on whichever site you want to report on:

1. Grab the latest release of the plugin from [github.com/coysh-digital/client-reporter-wordpress](https://github.com/coysh-digital/client-reporter-wordpress) (a ZIP of the `client-reporter-wordpress` directory).
2. In WordPress, head to **Plugins → Add New → Upload Plugin**, pick the ZIP and install it. (Or, if you'd rather, copy the plugin folder into `wp-content/plugins/` over SFTP.)
3. Activate the **Client Reporter Connector** plugin.

The plugin needs WordPress 6.0+ and PHP 7.4+. Once it's active, it registers a small read-only REST API under the `client-reporter/v1` namespace (for example `https://example.com/wp-json/client-reporter/v1/verify`). Until you've saved a connection code, every route returns `403` and nothing is exposed.

## Exchanging the connection code

Companion connectors authenticate with a single shared secret that Client Reporter calls the **connection code**. Client Reporter generates it; you paste it into the plugin. That same secret is what both ends use to sign and verify every request.

1. In Client Reporter, open the site, choose **Add integration → WordPress**, and enter the **WordPress site URL** (the public URL of the site, e.g. `https://example.com`).
2. Save. Client Reporter generates a random connection code and shows it on the setup screen. (Behind the scenes it stores this code as an encrypted credential — see [Security](../security/README.md).)
3. Over in WordPress, open **Settings → Client Reporter**, paste the connection code into the **Connection code** field, and press **Save connection code**. The screen will then show *Connection code saved*. WordPress stores the code in the `client_reporter_secret` option.
4. Come back to Client Reporter and press **Connect & verify**.

The connection code is a 48-character random string. Treat it like a password: anyone who has it and the site URL can read the data the connector exposes (but nothing more). You can rotate it whenever you like by re-generating it in Client Reporter and pasting the new value into the plugin.

## Verifying the connection

When you press **Connect & verify** in Client Reporter, it makes a signed `GET` request to the plugin's `verify` endpoint. The plugin checks the signature and responds with a small identifying payload. Client Reporter confirms that the response identifies as a WordPress Client Reporter connector before marking the connection as *Connected* and recording the plugin's version.

If verification fails, Client Reporter tells you why (wrong or rotated code, unreachable site, or an unexpected response). See [Troubleshooting](#troubleshooting) below.

## What the integration collects

Everything is pulled on Client Reporter's schedule; the plugin only ever responds. Here's what the WordPress connector reports:

**Site status** (from the `site` endpoint)

- WordPress core version and PHP version
- Site name and environment (production/staging/etc.)
- Active theme, and the total count of installed plugins
- User count and administrator count
- A basic Site Health indicator (good / attention)

**Available updates** (reported, never applied)

- Whether a WordPress core update is available
- The number of plugin updates and theme updates, plus a list of each (name, current version, available version)
- A combined "updates available" total for at-a-glance reporting

**WooCommerce sales** (from the `woocommerce` endpoint, only when WooCommerce is active)

For the report's date range, across completed and processing orders:

- Revenue and currency
- Order count and average order value
- Items sold and refund total
- Top-selling products (up to five, by revenue)

If WooCommerce isn't installed or active, the connector just reports that it's inactive and no store metrics show up.

## Security model

Client Reporter always **pulls**; the plugin only ever responds, read-only. Every request is signed with HMAC-SHA256 over the request method, path, timestamp, a random nonce and a hash of the (empty) body, using the shared connection code. The plugin rejects unsigned or wrongly-signed requests, requests whose timestamp is outside a ±300-second window, and replayed nonces.

For the full scheme — including how the connection code is stored encrypted at rest in Client Reporter — see [Security](../security/README.md).

## Troubleshooting

**"The website rejected the connection" / HTTP 401 or 403.** The connection code in Client Reporter doesn't match the one saved in the plugin, or no code has been saved yet. Re-copy the code from Client Reporter's setup screen into **Settings → Client Reporter** in WordPress, Save, and verify again.

**"Invalid signature" or "Request timestamp out of range" errors.** Signatures are time-sensitive: the plugin rejects any request whose timestamp differs from its own clock by more than 300 seconds. If the WordPress server's clock is badly out of sync (common on misconfigured or containerised hosts), fix the server time (NTP) and try again.

**"Nonce already used".** Each request carries a one-time nonce; this only shows up if a request is genuinely replayed. Just verify again — a fresh request uses a new nonce.

**"Could not reach the website".** Client Reporter couldn't connect to the site URL. Check the URL is correct and public, that the site is up, that the plugin is active, and that no firewall or security plugin is blocking the `/wp-json/client-reporter/v1/` REST routes.

**"Not as a WordPress Client Reporter connector".** The URL responded, but not with the expected connector payload — usually a wrong URL (pointing at a different site or a caching/placeholder page) or the plugin being inactive. Confirm the plugin is active and that `https://<your-site>/wp-json/client-reporter/v1/verify` is served by this WordPress install.
