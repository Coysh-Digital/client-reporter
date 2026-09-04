# Integrations

Integrations are how Client Reporter connects to the services behind your clients' websites and collects the data that reports are built from.

Each integration attaches to a Site, declares how it authenticates, and provides collectors that pull data in on a schedule. The bundled integrations are grouped by category: CMS, Analytics, Ecommerce and Monitoring. The WordPress and Craft CMS integrations connect through companion plugins that expose read-only data over HMAC-signed requests — Client Reporter never performs remote updates to a client's site.

Bundled integrations:

| Category   | Integrations                          |
| ---------- | ------------------------------------- |
| CMS        | WordPress, Craft CMS                  |
| Analytics  | Google Analytics 4, Plausible, Fathom |
| Ecommerce  | WooCommerce, Craft Commerce           |
| Monitoring | UptimeRobot                           |

Topics this section will cover:

- How integrations fit the Client → Sites → Integrations → Data → Reports model
- Adding and authenticating an integration on a Site
- The bundled integrations and their categories
- Companion plugins and the read-only, HMAC-signed model
- Per-integration guides: [WordPress](../wordpress/README.md), [Craft CMS](../craft/README.md), [Analytics](../analytics/README.md), [UptimeRobot](../uptime-robot/README.md)
- Building your own integration — see [Creating an integration](../creating-an-integration/README.md)
- Troubleshooting data collection — coming soon
