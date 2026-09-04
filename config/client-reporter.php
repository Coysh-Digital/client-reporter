<?php

declare(strict_types=1);
use App\Integrations\BetterUptime\BetterUptimeIntegration;
use App\Integrations\Craft\CraftCommerceIntegration;
use App\Integrations\Craft\CraftIntegration;
use App\Integrations\Fathom\FathomIntegration;
use App\Integrations\FreeAgent\FreeAgentIntegration;
use App\Integrations\GoogleAds\GoogleAdsIntegration;
use App\Integrations\GoogleAnalytics\GoogleAnalyticsIntegration;
use App\Integrations\GoogleSearchConsole\GoogleSearchConsoleIntegration;
use App\Integrations\Mailchimp\MailchimpIntegration;
use App\Integrations\Matomo\MatomoIntegration;
use App\Integrations\PageSpeed\PageSpeedIntegration;
use App\Integrations\Plausible\PlausibleIntegration;
use App\Integrations\Shopify\ShopifyIntegration;
use App\Integrations\Stripe\StripeIntegration;
use App\Integrations\Umami\UmamiIntegration;
use App\Integrations\UptimeKuma\UptimeKumaIntegration;
use App\Integrations\UptimeRobot\UptimeRobotIntegration;
use App\Integrations\WooCommerce\WooCommerceIntegration;
use App\Integrations\WordPress\WordPressIntegration;
use App\Integrations\Xero\XeroIntegration;
use App\Reporting\Blocks\Ads\AdsSummaryBlock;
use App\Reporting\Blocks\Analytics\AnalyticsChartBlock;
use App\Reporting\Blocks\Analytics\AnalyticsSummaryBlock;
use App\Reporting\Blocks\Analytics\CustomEventsBlock;
use App\Reporting\Blocks\Analytics\TopCountriesBlock;
use App\Reporting\Blocks\Analytics\TopDevicesBlock;
use App\Reporting\Blocks\Analytics\TopPagesBlock;
use App\Reporting\Blocks\Analytics\TrafficSourcesBlock;
use App\Reporting\Blocks\BillingBlock;
use App\Reporting\Blocks\ClosingBlock;
use App\Reporting\Blocks\ContentsBlock;
use App\Reporting\Blocks\CoverBlock;
use App\Reporting\Blocks\EcommerceBlock;
use App\Reporting\Blocks\Forms\LeadsSummaryBlock;
use App\Reporting\Blocks\Performance\PerformanceSummaryBlock;
use App\Reporting\Blocks\Search\SearchPerformanceBlock;
use App\Reporting\Blocks\TextBlock;
use App\Reporting\Blocks\Uptime\IncidentsBlock;
use App\Reporting\Blocks\Uptime\UptimeSummaryBlock;
use App\Reporting\Blocks\WebsiteOverviewBlock;

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    |
    | Client Reporter's own identity. This is never shown on client-facing
    | reports (those are fully white-labelled via the branding system); it is
    | only used inside the agency administration interface and by the GitHub
    | update checker. Keep `version` in sync with tagged releases.
    |
    */

    'name' => 'Client Reporter',

    'version' => '0.1.0-alpha.1',

    'repository' => 'coysh-digital/client-reporter',

    /*
    |--------------------------------------------------------------------------
    | Documentation links
    |--------------------------------------------------------------------------
    |
    | Where the UI sends people for help. `integrations` backs the "Add
    | integration" action on the integrations screen — building or installing a
    | new integration is a package/dev task, so it links out to the docs.
    |
    */
    'docs' => [
        'integrations' => env('CLIENT_REPORTER_DOCS_INTEGRATIONS', 'https://github.com/coysh-digital/client-reporter/blob/main/docs/integrations/README.md'),
    ],

    /*
    |--------------------------------------------------------------------------
    | First-party integrations
    |--------------------------------------------------------------------------
    |
    | Fully-qualified Integration class names bundled with Client Reporter.
    | Third-party integrations do NOT need to be listed here: they are
    | discovered automatically from installed Composer packages that declare
    | an "extra.client-reporter.integrations" array. Both sources are merged
    | by the IntegrationRegistry at runtime. Every entry must extend
    | App\Integrations\Contracts\Integration.
    |
    */

    'integrations' => [
        WordPressIntegration::class,
        CraftIntegration::class,
        GoogleAnalyticsIntegration::class,
        GoogleAdsIntegration::class,
        PlausibleIntegration::class,
        FathomIntegration::class,
        MatomoIntegration::class,
        UmamiIntegration::class,
        GoogleSearchConsoleIntegration::class,
        WooCommerceIntegration::class,
        CraftCommerceIntegration::class,
        ShopifyIntegration::class,
        StripeIntegration::class,
        FreeAgentIntegration::class,
        XeroIntegration::class,
        UptimeRobotIntegration::class,
        UptimeKumaIntegration::class,
        MailchimpIntegration::class,
        BetterUptimeIntegration::class,
        PageSpeedIntegration::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Core report blocks
    |--------------------------------------------------------------------------
    |
    | Block types always available in the report builder, regardless of which
    | integrations are connected. Integrations add their own blocks via
    | Integration::reportBlocks(); both are merged by the BlockTypeRegistry.
    |
    */

    'report_blocks' => [
        CoverBlock::class,
        ContentsBlock::class,
        TextBlock::class,
        WebsiteOverviewBlock::class,
        AnalyticsSummaryBlock::class,
        AdsSummaryBlock::class,
        AnalyticsChartBlock::class,
        TopPagesBlock::class,
        TrafficSourcesBlock::class,
        TopCountriesBlock::class,
        TopDevicesBlock::class,
        CustomEventsBlock::class,
        SearchPerformanceBlock::class,
        EcommerceBlock::class,
        LeadsSummaryBlock::class,
        UptimeSummaryBlock::class,
        IncidentsBlock::class,
        PerformanceSummaryBlock::class,
        BillingBlock::class,
        ClosingBlock::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data collection
    |--------------------------------------------------------------------------
    |
    | Collectors are dispatched by the `client-reporter:collect` command, which
    | the Laravel scheduler invokes every minute. On shared hosting a single
    | cron entry running the scheduler is enough; collectors run through the
    | database queue, which the scheduler drains each tick. VPS users may run a
    | persistent queue worker instead for lower latency.
    |
    */

    'collection' => [
        // Default cadence (minutes) at which a collector is considered "due"
        // when it does not declare its own interval.
        'default_interval' => 360,

        // How long collected metrics/snapshots are retained (days). Null keeps
        // everything, which is recommended so historical reports stay accurate.
        'retention_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Companion connectors (WordPress / Craft plugins)
    |--------------------------------------------------------------------------
    |
    | Client Reporter pulls read-only data from the companion plugins using
    | HMAC-signed requests. `timestamp_tolerance` bounds how far a request
    | timestamp may drift before it is rejected (replay/timestamp validation);
    | nonces are cached for the same window to reject replays. The compatibility
    | matrix lets the app tell an agency whether a connected plugin is
    | compatible and whether an update is available.
    |
    */

    'connectors' => [
        'timestamp_tolerance' => (int) env('CLIENT_REPORTER_CONNECTOR_TIMESTAMP_TOLERANCE', 300),

        'signature_algo' => 'sha256',

        'compatibility' => [
            'wordpress' => [
                'package' => 'client-reporter-wordpress',
                'minimum' => '0.1.0',
                'latest' => '0.1.0',
            ],
            'craft' => [
                'package' => 'client-reporter-craft',
                'minimum' => '0.1.0',
                'latest' => '0.1.0',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF rendering
    |--------------------------------------------------------------------------
    |
    | "dompdf" requires no external binaries and works on any shared host.
    | "browsershot" renders with headless Chromium for pixel-perfect Tailwind
    | output but needs Node + Chromium available on the server.
    |
    */

    'pdf' => [
        'driver' => env('CLIENT_REPORTER_PDF_DRIVER', 'dompdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public reports (secure share links)
    |--------------------------------------------------------------------------
    */

    'reports' => [
        'share_token_bytes' => 32,
        'default_share_expiry_days' => null, // null = no expiry by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Update checker
    |--------------------------------------------------------------------------
    |
    | Periodically checks the GitHub releases API for a newer stable release and
    | surfaces a notice to administrators. Client Reporter never updates itself.
    |
    */

    'updates' => [
        'enabled' => (bool) env('CLIENT_REPORTER_UPDATE_CHECK', true),
        'endpoint' => 'https://api.github.com/repos/coysh-digital/client-reporter/releases/latest',
        'check_interval_hours' => 24,
    ],

];
