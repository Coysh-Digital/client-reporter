# Craft CMS integration

The Craft CMS integration connects a Craft site to Client Reporter through a companion plugin, so you can include CMS data in your reports.

The companion plugin lives in a separate repository, [coysh-digital/client-reporter-craft](https://github.com/coysh-digital/client-reporter-craft). It exposes read-only data to Client Reporter over HMAC-signed requests. As with all companion connectors, Client Reporter only reads from the site — it never performs remote updates.

Topics this section will cover:

- Installing the Client Reporter companion plugin on a Craft CMS site
- Generating and exchanging the shared secret used to sign requests
- Connecting the site to Client Reporter and verifying the connection — coming soon
- What data the Craft integration collects — coming soon
- Using Craft Commerce data alongside it — see [Integrations](../integrations/README.md)
- The read-only, HMAC-signed security model — see [Security](../security/README.md)
- Troubleshooting the connection — coming soon
