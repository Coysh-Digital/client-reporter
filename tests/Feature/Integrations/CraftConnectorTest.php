<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Craft\CraftCommerceCollector;
use App\Integrations\Craft\CraftIntegration;
use App\Integrations\Craft\CraftStatusCollector;
use App\Livewire\Integrations\Setup;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CraftConnectorTest extends TestCase
{
    use RefreshDatabase;

    private function craftConnection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'craft',
            'name' => 'Craft CMS',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://craft.test'],
            'credentials' => ['secret' => 'craft-secret'],
        ]);
    }

    public function test_signature_uses_the_craft_path_prefix(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'craft', 'version' => '1.0.0'])]);

        (new SignedConnectorClient('https://craft.test', 'craft-secret', CraftIntegration::PATH_PREFIX))->get('verify');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/client-reporter/v1/verify')
            && $request->hasHeader('X-CR-Signature'));
    }

    public function test_verify_succeeds_against_a_craft_connector(): void
    {
        Http::fake(['craft.test/*' => Http::response([
            'ok' => true, 'connector' => 'craft', 'version' => '1.0.0', 'craft_version' => '5.3',
        ])]);

        $result = (new CraftIntegration)->verify($this->craftConnection());

        $this->assertTrue($result->ok);
        $this->assertSame('1.0.0', $result->meta['connector_version']);
    }

    public function test_verify_rejects_a_wordpress_response(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'wordpress'])]);

        $this->assertFalse((new CraftIntegration)->verify($this->craftConnection())->ok);
    }

    public function test_status_collector_reports_updates_and_queue(): void
    {
        Http::fake(['craft.test/*' => Http::response([
            'craft_version' => '5.3', 'php_version' => '8.3', 'environment' => 'production',
            'craft_update_available' => true, 'plugin_updates' => 2,
            'plugin_updates_list' => [['name' => 'seomatic', 'available' => '5.1']],
            'queue_pending' => 1, 'queue_failed' => 0, 'licence' => 'valid',
        ])]);

        $result = (new CraftStatusCollector)->collect($this->craftConnection(), new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertSame(1, (int) $metrics['cms.core_update_available']->value);
        $this->assertSame(3, (int) $metrics['cms.updates_total']->value); // 1 core + 2 plugins
        $this->assertSame('5.3', $result->snapshotPayload()['craft_version']);
    }

    public function test_commerce_collector_reads_sales_when_active(): void
    {
        Http::fake(['craft.test/*' => Http::response([
            'active' => true, 'currency' => 'GBP', 'revenue' => 2200.0, 'orders' => 30,
            'average_order_value' => 73.33, 'items_sold' => 55,
            'top_products' => [['name' => 'Ticket', 'quantity' => 20, 'revenue' => 400.0]],
        ])]);

        $result = (new CraftCommerceCollector)->collect($this->craftConnection(), new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertEqualsWithDelta(2200.0, $metrics['ecommerce.revenue']->value, 0.01);
        $this->assertTrue($result->snapshotPayload()['active']);
    }

    public function test_connecting_craft_generates_a_connection_code(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'craft', 'version' => '1.0.0', 'craft_version' => '5.3'])]);

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'craft'])
            ->set('values.base_url', 'https://craft.test')
            ->call('save')
            ->assertHasNoErrors();

        $connection = SiteIntegration::query()->where('integration_key', 'craft')->first();
        $this->assertNotNull($connection);
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertNotEmpty($connection->credential('secret'));
    }
}
