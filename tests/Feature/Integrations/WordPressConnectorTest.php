<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\WordPress\SiteStatusCollector;
use App\Integrations\WordPress\WooCommerceCollector;
use App\Integrations\WordPress\WordPressIntegration;
use App\Livewire\Integrations\Setup;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WordPressConnectorTest extends TestCase
{
    use RefreshDatabase;

    private function wpConnection(array $settings = [], array $credentials = []): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'wordpress',
            'name' => 'WordPress',
            'status' => ConnectionStatus::Connected,
            'settings' => array_merge(['base_url' => 'https://wp.test'], $settings),
            'credentials' => array_merge(['secret' => 'shared-secret'], $credentials),
        ]);
    }

    public function test_signature_matches_the_plugin_algorithm(): void
    {
        $client = new SignedConnectorClient('https://wp.test', 'shared-secret');

        $signature = $client->sign('GET', '/wp-json/client-reporter/v1/verify', '1700000000', 'abc123', '');

        // Independently recompute the way the WordPress plugin does.
        $payload = implode("\n", ['GET', '/wp-json/client-reporter/v1/verify', '1700000000', 'abc123', hash('sha256', '')]);
        $expected = hash_hmac('sha256', $payload, 'shared-secret');

        $this->assertSame($expected, $signature);
    }

    public function test_requests_carry_signature_timestamp_and_nonce_headers(): void
    {
        Http::fake(['wp.test/*' => Http::response(['ok' => true, 'connector' => 'wordpress', 'version' => '0.1.0'])]);

        (new SignedConnectorClient('https://wp.test', 'shared-secret'))->get('verify');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-CR-Signature')
                && $request->hasHeader('X-CR-Timestamp')
                && $request->hasHeader('X-CR-Nonce')
                && str_contains($request->url(), '/wp-json/client-reporter/v1/verify');
        });
    }

    public function test_verify_succeeds_against_a_wordpress_connector(): void
    {
        Http::fake(['wp.test/*' => Http::response([
            'ok' => true, 'connector' => 'wordpress', 'version' => '0.1.0', 'wordpress_version' => '6.6',
        ])]);

        $result = (new WordPressIntegration)->verify($this->wpConnection());

        $this->assertTrue($result->ok);
        $this->assertSame('0.1.0', $result->meta['connector_version']);
    }

    public function test_verify_rejects_a_non_connector_response(): void
    {
        Http::fake(['wp.test/*' => Http::response(['ok' => true, 'connector' => 'something-else'])]);

        $this->assertFalse((new WordPressIntegration)->verify($this->wpConnection())->ok);
    }

    public function test_verify_fails_on_401(): void
    {
        Http::fake(['wp.test/*' => Http::response(['error' => 'bad signature'], 401)]);

        $result = (new WordPressIntegration)->verify($this->wpConnection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rejected', $result->message);
    }

    public function test_site_status_collector_stores_metrics_and_snapshot(): void
    {
        Http::fake(['wp.test/*' => Http::response([
            'wordpress_version' => '6.6', 'php_version' => '8.2', 'active_theme' => 'Twenty Twenty-Four',
            'core_update_available' => true, 'plugin_updates' => 3, 'theme_updates' => 1,
            'plugin_updates_list' => [['name' => 'Yoast SEO', 'current' => '20.0', 'available' => '21.0']],
            'users' => 12, 'admins' => 2, 'site_health' => 'attention',
        ])]);

        $connection = $this->wpConnection();
        $result = (new SiteStatusCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(1, (int) $metrics['cms.core_update_available']->value);
        $this->assertSame(3, (int) $metrics['cms.plugin_updates']->value);
        $this->assertSame(5, (int) $metrics['cms.updates_total']->value); // 1 core + 3 plugins + 1 theme
        $this->assertSame('6.6', $result->snapshotPayload()['wordpress_version']);
    }

    public function test_woocommerce_collector_reads_sales_when_active(): void
    {
        Http::fake(['wp.test/*' => Http::response([
            'active' => true, 'currency' => 'GBP', 'revenue' => 4820.50, 'orders' => 63,
            'average_order_value' => 76.52, 'items_sold' => 128, 'refunds' => 120.0,
            'top_products' => [['name' => 'Roast beans 1kg', 'quantity' => 40, 'revenue' => 800.0]],
        ])]);

        $result = (new WooCommerceCollector)->collect($this->wpConnection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(4820.50, $metrics['ecommerce.revenue']->value, 0.01);
        $this->assertSame(63, (int) $metrics['ecommerce.orders']->value);
        $this->assertTrue($result->snapshotPayload()['active']);
    }

    public function test_woocommerce_collector_is_quiet_when_inactive(): void
    {
        Http::fake(['wp.test/*' => Http::response(['active' => false])]);

        $result = (new WooCommerceCollector)->collect($this->wpConnection(), new DateRange('2026-08-01', '2026-08-31'));

        $this->assertCount(0, $result->metrics());
        $this->assertFalse($result->snapshotPayload()['active']);
    }

    public function test_connecting_wordpress_generates_a_connection_code(): void
    {
        Http::fake(['wp.test/*' => Http::response([
            'ok' => true, 'connector' => 'wordpress', 'version' => '0.1.0', 'wordpress_version' => '6.6',
        ])]);

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'wordpress'])
            ->set('values.base_url', 'https://wp.test')
            ->call('save')
            ->assertHasNoErrors();

        $connection = SiteIntegration::query()->where('integration_key', 'wordpress')->first();
        $this->assertNotNull($connection);
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertNotEmpty($connection->credential('secret'));
        $this->assertSame('https://wp.test', $connection->setting('base_url'));
    }
}
