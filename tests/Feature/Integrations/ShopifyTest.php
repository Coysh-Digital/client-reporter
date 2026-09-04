<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Shopify\SalesCollector;
use App\Integrations\Shopify\ShopifyIntegration;
use App\Integrations\Support\IntegrationCategory;
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

class ShopifyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeShopify(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'shop.json')) {
                return Http::response(['shop' => ['name' => 'Northwind', 'currency' => 'GBP']]);
            }

            if (str_contains($url, 'orders.json')) {
                return Http::response(['orders' => [
                    [
                        'total_price' => '100.00',
                        'currency' => 'GBP',
                        'line_items' => [
                            ['title' => 'Beans', 'quantity' => 2, 'price' => '20.00'],
                            ['title' => 'Mug', 'quantity' => 1, 'price' => '15.00'],
                        ],
                    ],
                    [
                        'total_price' => '50.00',
                        'currency' => 'GBP',
                        'line_items' => [
                            ['title' => 'Beans', 'quantity' => 1, 'price' => '20.00'],
                        ],
                    ],
                ]]);
            }

            return Http::response([]);
        });
    }

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'shopify',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['access_token' => 'shpat_test'],
            'settings' => ['shop_domain' => 'northwind.myshopify.com'],
        ]);
    }

    public function test_shopify_is_registered_in_the_ecommerce_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Ecommerce);
        $this->assertContains('shopify', $keys);
    }

    public function test_shopify_verify_and_sales_collector(): void
    {
        $this->fakeShopify();
        $connection = $this->connection();

        $this->assertTrue((new ShopifyIntegration)->verify($connection)->ok);

        $result = (new SalesCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertSame(150.0, (float) $metrics['ecommerce.revenue']->value);
        $this->assertSame(2.0, (float) $metrics['ecommerce.orders']->value);
        $this->assertSame(75.0, (float) $metrics['ecommerce.aov']->value);
        $this->assertSame(4.0, (float) $metrics['ecommerce.items_sold']->value);

        $snapshot = $result->snapshotPayload();
        $this->assertSame('GBP', $snapshot['currency']);
        $this->assertSame('Beans', $snapshot['top_products'][0]['name']);
        $this->assertSame(3, $snapshot['top_products'][0]['quantity']);
    }

    public function test_collector_builds_a_daily_revenue_timeseries(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'shop.json')) {
                return Http::response(['shop' => ['name' => 'Northwind', 'currency' => 'GBP']]);
            }

            if (str_contains($url, 'orders.json')) {
                return Http::response(['orders' => [
                    ['total_price' => '100.00', 'currency' => 'GBP', 'created_at' => '2026-08-01T10:00:00+00:00', 'line_items' => []],
                    ['total_price' => '50.00', 'currency' => 'GBP', 'created_at' => '2026-08-01T15:00:00+00:00', 'line_items' => []],
                    ['total_price' => '25.00', 'currency' => 'GBP', 'created_at' => '2026-08-03T09:00:00+00:00', 'line_items' => []],
                ]]);
            }

            return Http::response([]);
        });

        $result = (new SalesCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-03'));

        $series = collect($result->snapshotPayload()['timeseries'])->keyBy('date');
        $this->assertCount(3, $series);
        $this->assertSame(150.0, $series['2026-08-01']['value']);
        $this->assertSame(0.0, $series['2026-08-02']['value']);
        $this->assertSame(25.0, $series['2026-08-03']['value']);
    }

    public function test_store_block_available_for_a_shopify_only_site(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'shopify',
            'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Store performance');
    }
}
