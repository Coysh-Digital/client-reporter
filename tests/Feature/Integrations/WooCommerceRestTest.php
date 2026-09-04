<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\WooCommerce\SalesCollector;
use App\Integrations\WooCommerce\WooCommerceIntegration;
use App\Livewire\Integrations\Catalog;
use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WooCommerceRestTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWoo(): void
    {
        Http::fake([
            '*/wp-json/wc/v3/reports/sales*' => Http::response([
                ['total_sales' => '1000.00', 'total_orders' => 20, 'total_items' => 50, 'total_refunds' => '25.00'],
            ]),
            '*/wp-json/wc/v3/reports/top_sellers*' => Http::response([
                ['title' => 'Beans', 'quantity' => 30],
                ['title' => 'Mug', 'quantity' => 12],
            ]),
            '*/wp-json/wc/v3/settings/general/woocommerce_currency*' => Http::response(['id' => 'woocommerce_currency', 'value' => 'GBP']),
        ]);
    }

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'woocommerce',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test'],
            'settings' => ['store_url' => 'https://shop.test'],
        ]);
    }

    public function test_woocommerce_is_registered_in_the_ecommerce_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Ecommerce);
        $this->assertContains('woocommerce', $keys);
    }

    public function test_woocommerce_verify_and_sales_collector(): void
    {
        $this->fakeWoo();
        $connection = $this->connection();

        $this->assertTrue((new WooCommerceIntegration)->verify($connection)->ok);

        $result = (new SalesCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertSame(1000.0, (float) $metrics['ecommerce.revenue']->value);
        $this->assertSame(20.0, (float) $metrics['ecommerce.orders']->value);
        $this->assertSame(50.0, (float) $metrics['ecommerce.aov']->value);
        $this->assertSame(50.0, (float) $metrics['ecommerce.items_sold']->value);

        $snapshot = $result->snapshotPayload();
        $this->assertSame('GBP', $snapshot['currency']);
        $this->assertSame('Beans', $snapshot['top_products'][0]['name']);
        $this->assertSame(30, $snapshot['top_products'][0]['quantity']);
        // Top sellers carry no per-product revenue.
        $this->assertNull($snapshot['top_products'][0]['revenue']);
    }

    public function test_store_block_available_for_a_woocommerce_rest_site(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'woocommerce',
            'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Store performance');
    }

    public function test_craft_commerce_is_a_provided_by_card_in_the_ecommerce_category(): void
    {
        $registry = app(IntegrationRegistry::class);
        $this->assertContains('craft_commerce', $registry->keysInCategory(IntegrationCategory::Ecommerce));

        $manifest = collect($registry->all())->first(fn ($i) => $i->key() === 'craft_commerce')?->manifest();
        $this->assertNotNull($manifest);
        $this->assertSame('craft', $manifest->providedBy);
    }

    public function test_catalog_shows_craft_commerce_as_provided_by_craft(): void
    {
        $manager = User::factory()->manager()->create();
        Site::factory()->create();

        Livewire::actingAs($manager)->test(Catalog::class)
            ->assertSee('Craft Commerce')
            ->assertSee('via Craft CMS');
    }
}
