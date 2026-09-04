# UptimeRobot integration

The UptimeRobot integration brings uptime and monitoring data into Client Reporter so it can show up in your client reports.

Client Reporter doesn't run its own uptime monitoring — it plugs into [UptimeRobot](https://uptimerobot.com/) instead. You connect your UptimeRobot account with an API key, map monitors to your Sites, and the integration collects the uptime and response data for your reports.

## Connecting your UptimeRobot account

UptimeRobot authenticates with an **API key**.

1. In UptimeRobot, open **My Settings** (top-right menu).
2. Under **API Settings**, create a **Main API Key** (or a Read-Only key) and copy it.
3. Paste it into the connection form. Optionally list specific **monitor IDs** to include — leave this blank to include every monitor on the account.
4. Press **Connect & verify**.

When you verify, Client Reporter lists the monitors the key can see and tells you how many it found, so you know the key works before the connection is saved.

## Mapping monitors to Sites

The **Monitor IDs** field is a per-site setting. You can either:

- **Leave it blank** to include all monitors on the account for that site, or
- **List specific monitor IDs** (comma-separated, e.g. `779035, 781394`) to report only those monitors.

You can also [connect UptimeRobot once for the whole workspace](../integrations/README.md#workspace-connections-connect-once). In that flow Client Reporter lists **every monitor on the account** and auto-matches each one to a Site by its URL — you confirm the mapping, and each site connection is created carrying just its matched monitor, all sharing the one API key.

## Metrics collected

The monitors collector turns raw monitor logs into client-friendly metrics for the reporting period:

- **Uptime percentage** — the average uptime ratio across the site's monitors.
- **Incidents** — the number of down events during the period.
- **Downtime** — total downtime, in seconds.
- **Response time** — the average response time, in milliseconds.
- **Monitor count** — how many monitors are included.

Alongside the metrics it keeps a snapshot with a per-monitor breakdown (name, URL, current status, uptime and average response) and an incident list (which monitor went down, when, for how long, and why).

## Uptime report blocks

These metrics feed the uptime blocks in the report builder:

- **Uptime summary** — headline uptime ratio, response time and incident count.
- **Incidents** — the list of downtime events over the period.

See [Reports](../reports/README.md) for adding these blocks.

## Also: Uptime Kuma and Better Uptime

UptimeRobot is one of three monitoring providers Client Reporter bundles in the **Monitoring** category. The uptime blocks are **generic across monitoring providers** — no provider registers its own blocks, so the same **Uptime summary** and **Incidents** blocks work whichever one you connect:

- **[Uptime Kuma](https://github.com/louislam/uptime-kuma)** — for a self-hosted Uptime Kuma instance. Needs the instance's public URL plus an API key (**Settings → API Keys**); monitors are picked by **name** rather than ID.
- **Better Uptime (Better Stack)** — needs an API token from **Better Stack → Settings → API tokens**; monitors are picked by ID, or left blank for the whole account.

All three support per-site and workspace-scoped connections, and all collect the same uptime/incident/response metrics, so you can just use whichever monitoring service you're already on without the reports looking any different.
