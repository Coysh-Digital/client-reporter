# Analytics integrations

The analytics integrations pull traffic and audience data into Client Reporter so it can show up in your client reports.

Client Reporter bundles **six** analytics providers: **Google Analytics 4**, **Google Ads**, **Plausible**, **Fathom**, **Matomo** and **Umami**. Each one connects using the provider's own API and authentication method, then collects the metrics that feed the analytics blocks in a report.

The two Google providers log in with **Google OAuth**; the four privacy-focused ones use an **API key or token** (plus an instance URL for self-hosted Matomo and Umami). Any of them can be [connected once for the whole workspace](../integrations/README.md#workspace-connections-connect-once) and auto-matched to your sites, or you can connect them one at a time per site.

## Google Analytics 4

- **Auth:** Google OAuth. Requires `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` to be configured on the installation.
- **What you need:** the numeric **GA4 property ID** (a per-site field), and a Google account with access to that property.

Connecting per site:

1. In Google Analytics, open **Admin → Property Settings** and copy the **Property ID** (a number like `123456789`).
2. Paste it into the connection form and press **Save**.
3. Click **Connect Google account** and sign in with an account that can view this property.
4. You come back connected.

In the workspace flow, you connect the Google account first, then click **Find sites** to list every GA4 property on the account — each one is auto-matched to a site by its web stream URL where it can be, and you confirm the mapping. (Properties with no web data stream get skipped, since there's no URL to match them to a site.)

## Google Ads

- **Auth:** Google OAuth (same Google credentials as GA4).
- **What you need:** the **Customer ID** of the account you want to report on (per site, shown top-right in Google Ads, e.g. `123-456-7890`) and a **Developer token** (a one-time, account-level secret, reused across every connection).

**About the developer token.** The developer token does not come from the advertiser account you're reporting on — it comes from a Google Ads **manager account** (also called an MCC account), and the **API Center** where you get it only appears on manager accounts. If you don't already have one, create a manager account first (it's free), then apply for the token once and reuse it for every Google Ads connection.

Connecting:

1. Copy the **Customer ID** of the account you want to report on and paste it into the form.
2. Sign in to your Google Ads **manager account** and open **[ads.google.com/aw/apicenter](https://ads.google.com/aw/apicenter)**. Complete the API access form and accept the terms. Use a working website URL and a regularly monitored contact email — if the URL isn't live or the inbox isn't watched, Google may reject the application, and its compliance team may email you for clarification during review.
3. When the token appears in the API Center, copy the **Developer token** and paste it into the form. New tokens are granted **Explorer** or **Test** access automatically. **Test** access only returns data for *test* accounts, so if you want to report on live campaigns, apply for **Basic** access (approval can take a little while).
4. Click **Connect Google account** and sign in with an account that can access the Google Ads account above. You come back connected.

To check you're in a manager account, open [ads.google.com/aw/apicenter](https://ads.google.com/aw/apicenter): a normal advertiser account shows *"The API Center is only available to manager accounts."*, and a red **Test account** label in the top-right means the account is a test manager or advertiser (which can't hold a usable production token).

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

Google's *search* data (clicks, impressions, click-through rate, average position and top queries) comes from **Google Search Console**, which is its own integration in the **Search** category — not one of the analytics providers above. It also uses Google OAuth and is matched to a verified property (a URL like `https://example.com/` or a domain property like `sc-domain:example.com`). It feeds the **Search performance** report block.

## Which report blocks analytics feeds

The metrics these integrations collect are what power the analytics blocks in the report builder:

- **Analytics summary** — headline visitors, sessions and page views.
- **Analytics chart** — the trend over the reporting period.
- **Top pages** — most-visited pages.
- **Traffic sources** — where visitors came from.
- **Top countries** and **Top devices** — audience breakdowns.
- **Custom events** — provider events where available.
- **Ads summary** — Google Ads spend, clicks, impressions and conversions.

See [Reports](../reports/README.md) for adding and configuring these blocks.
