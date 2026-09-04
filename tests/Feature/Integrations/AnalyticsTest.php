<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Fathom\FathomCollector;
use App\Integrations\GoogleAnalytics\GoogleAnalyticsCollector;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Matomo\MatomoCollector;
use App\Integrations\Matomo\MatomoIntegration;
use App\Integrations\Plausible\PlausibleCollector;
use App\Integrations\Plausible\PlausibleIntegration;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Umami\UmamiCollector;
use App\Integrations\Umami\UmamiIntegration;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\ReportGenerator;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $range;

    protected function setUp(): void
    {
        parent::setUp();
        $this->range = new DateRange('2026-08-01', '2026-08-31');
    }

    private function connection(string $key, array $settings, array $credentials): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => $key,
            'status' => ConnectionStatus::Connected,
            'settings' => $settings,
            'credentials' => $credentials,
        ]);
    }

    public function test_analytics_providers_are_registered_in_the_analytics_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Analytics);

        $this->assertContains('plausible', $keys);
        $this->assertContains('fathom', $keys);
        $this->assertContains('google_analytics', $keys);
        $this->assertContains('matomo', $keys);
        $this->assertContains('umami', $keys);
    }

    public function test_matomo_verify_and_collector(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $method = $q['method'] ?? '';
            $period = $q['period'] ?? 'range';

            return match (true) {
                $method === 'VisitsSummary.get' && $period === 'day' => Http::response(['2026-08-01' => ['nb_uniq_visitors' => 20]]),
                $method === 'VisitsSummary.get' => Http::response(['nb_visits' => 1500, 'nb_uniq_visitors' => 1200, 'nb_actions' => 3400, 'bounce_rate' => '40%', 'avg_time_on_site' => 120]),
                $method === 'Actions.get' => Http::response(['nb_pageviews' => 3400]),
                $method === 'Actions.getPageUrls' => Http::response([['label' => '/pricing', 'nb_visits' => 120, 'nb_hits' => 200]]),
                $method === 'Referrers.getReferrerType' => Http::response([['label' => 'Search Engines', 'nb_visits' => 300]]),
                default => Http::response([]),
            };
        });

        $connection = $this->connection('matomo', ['base_url' => 'https://analytics.example.com', 'site_id' => '1'], ['token' => 'tok']);

        $this->assertTrue((new MatomoIntegration)->verify($connection)->ok);

        $result = (new MatomoCollector)->collect($connection, $this->range);
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(1200, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(3400, $metrics['analytics.pageviews']->value, 0.01);
        $this->assertEqualsWithDelta(40, $metrics['analytics.bounce_rate']->value, 0.01);
        $this->assertSame('Matomo', $result->snapshotPayload()['provider']);
        $this->assertNotEmpty($result->snapshotPayload()['top_pages']);
        $this->assertNotEmpty($result->snapshotPayload()['timeseries']);
    }

    public function test_umami_verify_and_collector(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/stats') => Http::response(['pageviews' => ['value' => 3400], 'visitors' => ['value' => 1200], 'visits' => ['value' => 1500], 'bounces' => ['value' => 600], 'totaltime' => ['value' => 180000]]),
                str_contains($url, 'type=referrer') => Http::response([['x' => 'google.com', 'y' => 300]]),
                str_contains($url, 'type=url') => Http::response([['x' => '/pricing', 'y' => 200]]),
                str_contains($url, '/pageviews') => Http::response(['pageviews' => [['x' => '2026-08-01', 'y' => 100]], 'sessions' => [['x' => '2026-08-01', 'y' => 20]]]),
                default => Http::response([]),
            };
        });

        $connection = $this->connection('umami', ['website_id' => 'w1', 'base_url' => 'https://api.umami.is/v1'], ['api_key' => 'k']);

        $this->assertTrue((new UmamiIntegration)->verify($connection)->ok);

        $result = (new UmamiCollector)->collect($connection, $this->range);
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(1200, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(3400, $metrics['analytics.pageviews']->value, 0.01);
        $this->assertEqualsWithDelta(40, $metrics['analytics.bounce_rate']->value, 0.01); // 600 / 1500 * 100
        $this->assertSame('Umami', $result->snapshotPayload()['provider']);
        $this->assertNotEmpty($result->snapshotPayload()['sources']);
    }

    public function test_plausible_verify_and_collector(): void
    {
        Http::fake([
            '*stats/aggregate*' => Http::response(['results' => [
                'visitors' => ['value' => 1200], 'pageviews' => ['value' => 3400], 'visits' => ['value' => 1500],
                'bounce_rate' => ['value' => 45], 'visit_duration' => ['value' => 90],
            ]]),
            '*stats/breakdown*' => Http::response(['results' => [['page' => '/', 'source' => 'Google', 'visitors' => 800, 'pageviews' => 1500]]]),
            '*stats/timeseries*' => Http::response(['results' => [['date' => '2026-08-01', 'visitors' => 40]]]),
        ]);

        $connection = $this->connection('plausible', ['site_id' => 'example.com'], ['api_token' => 'tok']);

        $this->assertTrue((new PlausibleIntegration)->verify($connection)->ok);

        $result = (new PlausibleCollector)->collect($connection, $this->range);
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(1200, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(3400, $metrics['analytics.pageviews']->value, 0.01);
        $this->assertSame('Plausible', $result->snapshotPayload()['provider']);
        $this->assertNotEmpty($result->snapshotPayload()['top_pages']);
    }

    public function test_fathom_collector(): void
    {
        Http::fake(['api.usefathom.com/*' => Http::response([
            ['visits' => 1500, 'uniques' => 1200, 'pageviews' => 3400, 'avg_duration' => 95, 'bounce_rate' => 42],
        ])]);

        $connection = $this->connection('fathom', ['site_id' => 'ABCDEFG'], ['api_token' => 'tok']);
        $result = (new FathomCollector)->collect($connection, $this->range);
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(1200, $metrics['analytics.visitors']->value, 0.01);
        $this->assertSame('Fathom', $result->snapshotPayload()['provider']);
    }

    public function test_google_analytics_collector(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'access-token']);
            }

            $dimensions = collect($request->data()['dimensions'] ?? [])->pluck('name')->all();

            if ($dimensions === []) {
                return Http::response([
                    'metricHeaders' => [
                        ['name' => 'activeUsers'], ['name' => 'screenPageViews'], ['name' => 'sessions'],
                        ['name' => 'bounceRate'], ['name' => 'averageSessionDuration'],
                    ],
                    'rows' => [['metricValues' => [['value' => '900'], ['value' => '2600'], ['value' => '1100'], ['value' => '0.4'], ['value' => '85']]]],
                ]);
            }

            return Http::response(['rows' => [[
                'dimensionValues' => [['value' => '/']],
                'metricValues' => [['value' => '500'], ['value' => '300']],
            ]]]);
        });

        $connection = $this->connection('google_analytics', ['property_id' => '123456'], ['refresh_token' => 'refresh']);
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $result = (new GoogleAnalyticsCollector)->collect($connection, $this->range);
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(900, $metrics['analytics.visitors']->value, 0.01);
        $this->assertEqualsWithDelta(40, $metrics['analytics.bounce_rate']->value, 0.01); // 0.4 * 100
        $this->assertSame('Google Analytics', $result->snapshotPayload()['provider']);
    }

    public function test_google_analytics_collects_countries_devices_and_events(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'access-token']);
            }

            $dimensions = collect($request->data()['dimensions'] ?? [])->pluck('name')->all();

            return match ($dimensions) {
                [] => Http::response([
                    'metricHeaders' => [['name' => 'activeUsers'], ['name' => 'screenPageViews'], ['name' => 'sessions'], ['name' => 'bounceRate'], ['name' => 'averageSessionDuration']],
                    'rows' => [['metricValues' => [['value' => '900'], ['value' => '2600'], ['value' => '1100'], ['value' => '0.4'], ['value' => '85']]]],
                ]),
                ['country'] => Http::response(['rows' => [['dimensionValues' => [['value' => 'United States']], 'metricValues' => [['value' => '600']]]]]),
                ['deviceCategory'] => Http::response(['rows' => [['dimensionValues' => [['value' => 'mobile']], 'metricValues' => [['value' => '700']]]]]),
                ['eventName'] => Http::response(['rows' => [['dimensionValues' => [['value' => 'sign_up']], 'metricValues' => [['value' => '15']]]]]),
                default => Http::response(['rows' => [['dimensionValues' => [['value' => '/']], 'metricValues' => [['value' => '500'], ['value' => '300']]]]]),
            };
        });

        $connection = $this->connection('google_analytics', ['property_id' => '123456'], ['refresh_token' => 'refresh']);
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $payload = (new GoogleAnalyticsCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame('United States', $payload['countries'][0]['label']);
        $this->assertSame(600, $payload['countries'][0]['visitors']);
        $this->assertSame('Mobile', $payload['devices'][0]['label']);
        $this->assertSame('sign_up', $payload['events'][0]['label']);
        $this->assertSame(15, $payload['events'][0]['count']);
    }

    public function test_plausible_collects_countries_devices_and_events(): void
    {
        Http::fake([
            '*stats/aggregate*' => Http::response(['results' => ['visitors' => ['value' => 500], 'pageviews' => ['value' => 900], 'visits' => ['value' => 600], 'bounce_rate' => ['value' => 40], 'visit_duration' => ['value' => 70]]]),
            '*property=visit%3Acountry*' => Http::response(['results' => [['country' => 'France', 'visitors' => 40]]]),
            '*property=visit%3Adevice*' => Http::response(['results' => [['device' => 'Desktop', 'visitors' => 60]]]),
            '*property=event%3Aname*' => Http::response(['results' => [['name' => 'Signup', 'visitors' => 12]]]),
            '*stats/breakdown*' => Http::response(['results' => [['page' => '/', 'source' => 'Google', 'visitors' => 800, 'pageviews' => 1500]]]),
            '*stats/timeseries*' => Http::response(['results' => []]),
        ]);

        $connection = $this->connection('plausible', ['site_id' => 'example.com'], ['api_token' => 'tok']);

        $payload = (new PlausibleCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame('France', $payload['countries'][0]['label']);
        $this->assertSame('Desktop', $payload['devices'][0]['label']);
        $this->assertSame('Signup', $payload['events'][0]['label']);
    }

    public function test_fathom_reports_countries_devices_and_events(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/events') && ! str_contains($url, 'entity=event')) {
                return Http::response(['data' => [['name' => 'Signup'], ['name' => 'Purchase']]]);
            }

            if (($data['entity'] ?? null) === 'event') {
                return Http::response([
                    ($data['entity_name'] ?? '') === 'Signup'
                        ? ['conversions' => '15']
                        : ['conversions' => '0'],
                ]);
            }

            return match ($data['field_grouping'] ?? null) {
                'country_code' => Http::response([['country_code' => 'DE', 'uniques' => 30]]),
                'device_type' => Http::response([['device_type' => 'Mobile', 'uniques' => 50]]),
                default => Http::response([['visits' => 1500, 'uniques' => 1200, 'pageviews' => 3400, 'avg_duration' => 95, 'bounce_rate' => 42]]),
            };
        });

        $connection = $this->connection('fathom', ['site_id' => 'ABCDEFG'], ['api_token' => 'tok']);
        $payload = (new FathomCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame('DE', $payload['countries'][0]['label']);
        $this->assertSame('Mobile', $payload['devices'][0]['label']);
        $this->assertSame('Signup', $payload['events'][0]['label']);
        $this->assertSame(15, $payload['events'][0]['count']);
        $this->assertCount(1, $payload['events'], 'Purchase had 0 conversions and should be omitted.');
    }

    public function test_matomo_collects_countries_devices_and_events(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            return match ($q['method'] ?? '') {
                'VisitsSummary.get' => Http::response(['nb_visits' => 1500, 'nb_uniq_visitors' => 1200, 'nb_actions' => 3400, 'bounce_rate' => '40%', 'avg_time_on_site' => 120]),
                'Actions.get' => Http::response(['nb_pageviews' => 3400]),
                'UserCountry.getCountry' => Http::response([['label' => 'Spain', 'nb_visits' => 25]]),
                'DevicesDetection.getType' => Http::response([['label' => 'Smartphone', 'nb_visits' => 45]]),
                'Events.getAction' => Http::response([['label' => 'Download', 'nb_events' => 9]]),
                default => Http::response([]),
            };
        });

        $connection = $this->connection('matomo', ['base_url' => 'https://analytics.example.com', 'site_id' => '1'], ['token' => 'tok']);
        $payload = (new MatomoCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame('Spain', $payload['countries'][0]['label']);
        $this->assertSame('Smartphone', $payload['devices'][0]['label']);
        $this->assertSame('Download', $payload['events'][0]['label']);
        $this->assertSame(9, $payload['events'][0]['count']);
    }

    public function test_matomo_degrades_gracefully_when_a_breakdown_plugin_is_disabled(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

            if (($q['method'] ?? '') === 'UserCountry.getCountry') {
                return Http::response(['result' => 'error', 'message' => "The method 'getCountry' does not exist."]);
            }

            return Http::response(['nb_visits' => 100, 'nb_uniq_visitors' => 80]);
        });

        $connection = $this->connection('matomo', ['base_url' => 'https://analytics.example.com', 'site_id' => '1'], ['token' => 'tok']);
        $payload = (new MatomoCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame([], $payload['countries']);
    }

    public function test_umami_collects_countries_devices_and_events(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/stats') => Http::response(['pageviews' => ['value' => 3400], 'visitors' => ['value' => 1200], 'visits' => ['value' => 1500], 'bounces' => ['value' => 600], 'totaltime' => ['value' => 180000]]),
                str_contains($url, 'type=country') => Http::response([['x' => 'Canada', 'y' => 40]]),
                str_contains($url, 'type=device') => Http::response([['x' => 'desktop', 'y' => 55]]),
                str_contains($url, 'type=event') => Http::response([['x' => 'purchase', 'y' => 7]]),
                default => Http::response([]),
            };
        });

        $connection = $this->connection('umami', ['website_id' => 'w1', 'base_url' => 'https://api.umami.is/v1'], ['api_key' => 'k']);
        $payload = (new UmamiCollector)->collect($connection, $this->range)->snapshotPayload();

        $this->assertSame('Canada', $payload['countries'][0]['label']);
        $this->assertSame('Desktop', $payload['devices'][0]['label']);
        $this->assertSame('purchase', $payload['events'][0]['label']);
        $this->assertSame(7, $payload['events'][0]['count']);
    }

    public function test_report_resolves_analytics_via_whichever_provider_is_connected(): void
    {
        Http::fake([
            '*stats/aggregate*' => Http::response(['results' => ['visitors' => ['value' => 500], 'pageviews' => ['value' => 900], 'visits' => ['value' => 600], 'bounce_rate' => ['value' => 40], 'visit_duration' => ['value' => 70]]]),
            '*stats/breakdown*' => Http::response(['results' => [['page' => '/pricing', 'visitors' => 120, 'pageviews' => 200]]]),
            '*stats/timeseries*' => Http::response(['results' => [['date' => '2026-08-01', 'visitors' => 20]]]),
        ]);

        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'plausible', 'status' => ConnectionStatus::Connected,
            'settings' => ['site_id' => 'example.com'], 'credentials' => ['api_token' => 'tok'],
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'analytics.summary', 'position' => 0]);
        $report->blocks()->create(['type' => 'analytics.top_pages', 'position' => 1]);

        app(ReportGenerator::class)->generate($report);

        $summary = collect($report->latestRender->data)->firstWhere('type', 'analytics.summary');
        $this->assertTrue($summary['data']['has_data']);
        $visitors = collect($summary['data']['metrics'])->firstWhere('label', 'Visitors');
        $this->assertEqualsWithDelta(500, $visitors['current'], 0.01);
        $this->assertSame('Plausible', $summary['data']['provider']);
    }
}
