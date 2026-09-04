# Client Reporter

![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20.svg)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg)

**Open-source, self-hosted client reporting for web agencies.**

Client Reporter connects your clients' websites, analytics, ecommerce and uptime services and turns the data into beautifully branded reports.

> **Screenshots coming soon.**

## What is Client Reporter?

Client Reporter is a self-hosted Laravel application that connects the services your clients' websites already run on — their CMS, analytics, ecommerce platform and uptime monitoring — collects data from them on a schedule, and turns that data into attractive, fully white-labelled client reports.

It is MIT licensed, free, and has no paid tiers and no licence keys. One installation belongs to one agency; it is not a multi-tenant SaaS.

Client Reporter is deliberately narrow. It does one job — client reporting — and aims to do it well.

## Who is it for?

- **Web agencies** that maintain a portfolio of client websites and want a consistent, professional reporting story.
- **Freelance web developers** who want to give clients a branded, self-hosted report without paying a monthly SaaS fee.

## What it does

- Organises your work around a simple model: **Client → Sites → Integrations → collected Data → Reports**.
- Connects to the CMS, analytics, ecommerce and monitoring services behind each site through bundled integrations.
- Collects data on a schedule using Laravel's scheduler — no persistent processes required on shared hosting.
- Produces **fully white-labelled** client reports that can be branded entirely as your agency, with no Client Reporter references.
- Supports staff roles — **Administrator**, **Manager** and **Viewer** — plus a restricted **Client portal** role for the people you report to.

## What Client Reporter deliberately does not do

Client Reporter is intentionally focused. It does **not** provide, and is not intended to provide:

- GitHub integration
- Deployment tracking
- Server monitoring
- SSH or remote command execution
- Backups
- Malware or vulnerability scanning
- Remote CMS updates or plugin installation
- Uptime monitoring infrastructure (it integrates with services like UptimeRobot, Uptime Kuma and BetterUptime instead)
- AI- or LLM-generated commentary (report summaries are generated deterministically from your own numbers, so there is nothing to hallucinate)
- Being your invoicing or accounting system (it keeps a lightweight invoice ledger and can sync invoices from FreeAgent or Xero purely so they can appear in reports — it is not a billing product)
- CRM
- Project management
- An integration marketplace

The companion plugins are **read-only**. Client Reporter never performs remote updates to your clients' sites.

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm (for building front-end assets)
- One of: SQLite (default, easiest), MySQL/MariaDB, or PostgreSQL
- A web server able to serve the `public/` directory

No Docker is required. On shared hosting, a single cron entry drives everything:

```
* * * * * php /path/to/artisan schedule:run
```

On a VPS you may optionally run a persistent queue worker. By default Client Reporter uses the database cache, session and queue drivers, so Redis is not required. PDF rendering is pluggable: dompdf is used by default (no binaries, shared-host-safe), with Browsershot available as an option on a VPS.

## Installation

```bash
git clone https://github.com/coysh-digital/client-reporter.git
cd client-reporter
composer install
npm install && npm run build
```

Then point your web server's document root at the `public/` directory and open the site in your browser to run the install wizard.

For full step-by-step instructions, see [docs/installation](docs/installation/README.md).

## Integrations

Bundled integrations, grouped by category:

| Category      | Integrations                                                          |
| ------------- | --------------------------------------------------------------------- |
| CMS           | WordPress, Craft CMS                                                   |
| Analytics     | Google Analytics 4, Google Ads, Plausible, Fathom, Matomo, Umami      |
| Search        | Google Search Console                                                  |
| Ecommerce     | WooCommerce, Craft Commerce, Shopify, Stripe                          |
| Forms & Leads | Mailchimp                                                              |
| Monitoring    | UptimeRobot, Uptime Kuma, BetterUptime                                |
| Performance   | PageSpeed Insights                                                     |
| Billing       | FreeAgent, Xero                                                        |

Analytics- and billing-style integrations can be connected **once for the whole workspace** (a single API key or OAuth login) and auto-matched to your sites and clients by URL or email, or connected individually per site. The WordPress and Craft CMS integrations connect through companion plugins that live in separate repositories — [coysh-digital/client-reporter-wordpress](https://github.com/coysh-digital/client-reporter-wordpress) and [coysh-digital/client-reporter-craft](https://github.com/coysh-digital/client-reporter-craft). They expose **read-only** data over HMAC-signed requests.

Already using another tool to manage your sites? You can **bulk-import** them from MainWP, ManageWP or WPMgr.

## White-labelling

White-labelling is a headline feature. Client-facing reports can be fully branded as your agency — your logo, your colours, your name — with no Client Reporter references anywhere your clients can see. Your clients experience the report as yours.

See [docs/branding](docs/branding/README.md) for details.

## Updating

Client Reporter is updated by pulling the latest code and running the update steps. See [docs/updating](docs/updating/README.md).

## Extending with integrations

Client Reporter has an Integration SDK. Third-party integrations are installable Composer packages, discovered via `extra.client-reporter.integrations` in their `composer.json`. A generator, `php artisan client-reporter:make-integration`, scaffolds a new integration for you.

See [docs/creating-an-integration](docs/creating-an-integration/README.md) to get started.

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup, coding standards and pull request process, and our [Code of Conduct](CODE_OF_CONDUCT.md).

## Security

If you discover a security vulnerability, please follow the responsible disclosure process in [SECURITY.md](SECURITY.md). Please do not open public issues for security problems.

## License

Client Reporter is open-source software licensed under the [MIT license](LICENSE).

Copyright (c) 2026 Tim Coysh
