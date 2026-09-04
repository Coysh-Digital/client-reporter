# WordPress integration

The WordPress integration connects a WordPress site to Client Reporter through a companion plugin, so you can include CMS data in your reports.

The companion plugin lives in a separate repository, [coysh-digital/client-reporter-wordpress](https://github.com/coysh-digital/client-reporter-wordpress). It exposes read-only data to Client Reporter over HMAC-signed requests. Client Reporter only ever reads from the site — it never performs updates, installs plugins or makes changes of any kind.

Topics this section will cover:

- Installing the Client Reporter companion plugin on a WordPress site
- Generating and exchanging the shared secret used to sign requests
- Connecting the site to Client Reporter and verifying the connection — coming soon
- What data the WordPress integration collects — coming soon
- Using WooCommerce data alongside it — see [Integrations](../integrations/README.md)
- The read-only, HMAC-signed security model — see [Security](../security/README.md)
- Troubleshooting the connection — coming soon
