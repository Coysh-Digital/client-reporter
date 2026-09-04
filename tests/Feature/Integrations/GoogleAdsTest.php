<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\GoogleAds\GoogleAdsCollector;
use App\Integrations\GoogleAds\GoogleAdsIntegration;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
    }

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'credentials' => ['refresh_token' => 'a-refresh-token', 'developer_token' => 'dev-token'],
            'settings' => ['customer_id' => '123-456-7890'],
        ]);
    }

    private function fakeGoogle(array $results): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'an-access-token']),
            'googleads.googleapis.com/*' => Http::response(['results' => $results]),
        ]);
    }

    public function test_verify_fails_when_oauth_is_not_configured(): void
    {
        Config::set('services.google.client_id', null);

        $result = (new GoogleAdsIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', $result->message);
    }

    public function test_verify_fails_when_not_yet_connected_via_oauth(): void
    {
        $connection = SiteIntegration::factory()->create(['credentials' => []]);

        $result = (new GoogleAdsIntegration)->verify($connection);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Connect your Google account', $result->message);
    }

    public function test_verify_succeeds_and_reports_access_denied_clearly(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'an-access-token']),
            'googleads.googleapis.com/*' => Http::response('', 403),
        ]);

        $result = (new GoogleAdsIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('denied access', $result->message);
    }

    public function test_collector_sums_daily_rows_into_period_totals(): void
    {
        $this->fakeGoogle([
            ['customer' => ['currencyCode' => 'GBP'], 'metrics' => ['costMicros' => '5000000', 'clicks' => '10', 'impressions' => '1000', 'conversions' => 2]],
            ['customer' => ['currencyCode' => 'GBP'], 'metrics' => ['costMicros' => '3000000', 'clicks' => '8', 'impressions' => '800', 'conversions' => 1]],
        ]);

        $result = (new GoogleAdsCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(8.0, $metrics['ads.spend']->value, 0.001);
        $this->assertSame('GBP', $metrics['ads.spend']->unit);
        $this->assertSame(18, (int) $metrics['ads.clicks']->value);
        $this->assertSame(1800, (int) $metrics['ads.impressions']->value);
        $this->assertEqualsWithDelta(3.0, $metrics['ads.conversions']->value, 0.001);
    }
}
