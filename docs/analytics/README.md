# Analytics integrations

The analytics integrations pull traffic and audience data into Client Reporter so it can appear in your client reports.

Client Reporter bundles integrations for **six** analytics providers: **Google Analytics 4**, **Google Ads**, **Plausible**, **Fathom**, **Matomo** and **Umami**. Each connects using the provider's own API and authentication method, then collects the metrics that power the analytics blocks in a report.

The two Google providers authenticate with **Google OAuth**; the four privacy-focused providers use an **API key or token** (plus an instance URL for self-hosted Matomo and Umami). All of them can be [connected once for the whole workspace](../integrations/README.md#workspace-connections-connect-once) and auto-matched to your sites, or connected individually per site.

## Google Analytics 4

- **Auth:** Google OAuth. Requires `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` to be configured on the installation.
- **What you need:** the numeric **GA4 property ID** (a per-site field), and a Google account with access to that property.

Connecting per site:

1. In Google Analytics, open **Admin → Property Settings** and copy the **Property ID** (a number like `123456789`).
2. Paste it into the connection form and press **Save**.
3. Click **Connect Google account** and sign in with an account that can view this property.
4. You return connected.

In the workspace flow, you connect the Google account first, then click **Find sites** to list every GA4 property on the account — each is auto-matched to a site by its web stream URL where possible, and you confirm the mapping. (Properties with no web data stream are skipped, since they can't be matched to a site's URL.)

## Google Ads

- **Auth:** Google OAuth (same Google credentials as GA4).
- **What you need:** the **Customer ID** (per site, shown top-right in Google Ads, e.g. `123-456-7890`) and a **Developer token** (an account-level secret from the API Center, reused across every connection).

Connecting:

1. In Google Ads, copy the account's **Customer ID** and paste it into the form.
2. Under **Tools & Settings → API Center**, copy your **Developer token** and paste it in.
3. Click **Connect Google account** and sign in with an account that can access this Google Ads account.
4. You return connected.

Google Ads feeds the **Ads summary** block (spend, clicks, impressions and conversions) rather than the general analytics blocks.

## Plausible

- **Auth:** API key (a Stats API key).
- **What you need:** the API key (account-level), the **Site ID** (your site's domain, e.g. `example.com`, per site), and — only for self-hosted Plausible — the instance URL. Leave the URL blank for `plausible.io`.

Connecting:

1. In Plausible, open **Settings → API keys** (top-right account menu).
2. Click **+ New API key**, name it and copy the key.
3. Enter the key and your site's domain (its "Site ID") in the form, then **Connect & verify**.

## Fathom

- **Auth:** API token.
- **What you need:** the API token (account-level) and the **Site ID** (a short code like `ABCDEFG`, per site).

Connecting:

1. In Fathom, click your email (top-right) then **Settings → API**.
2. Create an **API token** and copy it.
3. Find your **Site ID** under **Settings → Sites**.
4. Paste the token and Site ID into the form, then **Connect & verify**.

## Matomo

- **Auth:** API auth token. Works with Matomo Cloud and self-hosted Matomo.
- **What you need:** your **Matomo URL** (e.g. `https://analytics.example.com`), an **auth token** (account-level), and the numeric **Site ID / idSite** (per site).

Connecting:

1. In Matomo, click the cog (Administration), then **Personal → Security**.
2. Under **Auth tokens**, click **Create new token** and copy it.
3. Find your site's numeric ID under **Websites → Manage** (the "ID" column).
4. Paste your Matomo URL, the token and the site ID into the form, then **Connect & verify**.

## Umami

- **Auth:** API key. Works with Umami Cloud and self-hosted Umami.
- **What you need:** the API key (account-level), the **Website ID** (the website's UUID, per site), and — only for self-hosted instances — the **API base URL**. Leave the base URL blank for Umami Cloud.

Connecting:

1. On Umami Cloud, open **Settings → API keys** and create a key. (Self-hosted: create an API key and set the base URL below.)
2. Open your website in Umami and copy its **Website ID** from **Settings → Websites**.
3. Paste the API key and website ID into the form, then **Connect & verify**.

## Search Console is separate

Google's *search* data (clicks, impressions, click-through rate, average position and top queries) comes from **Google Search Console**, which is a distinct integration in the **Search** category, not one of the analytics providers above. It also uses Google OAuth and is matched to a verified property (a URL like `https://example.com/` or a domain property like `sc-domain:example.com`). It feeds the **Search performance** report block.

## Which report blocks analytics feeds

The metrics these integrations collect power the analytics blocks in the report builder:

- **Analytics summary** — headline visitors, sessions and page views.
- **Analytics chart** — the trend over the reporting period.
- **Top pages** — most-visited pages.
- **Traffic sources** — where visitors came from.
- **Top countries** and **Top devices** — audience breakdowns.
- **Custom events** — provider events where available.
- **Ads summary** — Google Ads spend, clicks, impressions and conversions.

See [Reports](../reports/README.md) for adding and configuring these blocks.
