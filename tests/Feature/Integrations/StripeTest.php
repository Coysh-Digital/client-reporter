<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Stripe\SalesCollector;
use App\Integrations\Stripe\StripeIntegration;
use App\Integrations\Support\IntegrationCategory;
use App\Livewire\Reports\Builder;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStripe(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['has_more' => false, 'data' => [
                ['id' => 'ch_1', 'status' => 'succeeded', 'paid' => true, 'currency' => 'gbp', 'amount' => 10000, 'amount_refunded' => 0],
                ['id' => 'ch_2', 'status' => 'succeeded', 'paid' => true, 'currency' => 'gbp', 'amount' => 5000, 'amount_refunded' => 500],
                ['id' => 'ch_3', 'status' => 'failed', 'paid' => false, 'currency' => 'gbp', 'amount' => 9900, 'amount_refunded' => 0],
            ]]),
        ]);
    }

    private function connection(): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'stripe',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'rk_test'],
        ]);
    }

    public function test_stripe_is_registered_in_the_ecommerce_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Ecommerce);
        $this->assertContains('stripe', $keys);
    }

    public function test_stripe_verify_and_payments_collector(): void
    {
        $this->fakeStripe();
        $connection = $this->connection();

        $this->assertTrue((new StripeIntegration)->verify($connection)->ok);

        $result = (new SalesCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        // Only the two succeeded/paid charges count: £150 gross across 2 payments.
        $this->assertSame(150.0, (float) $metrics['ecommerce.revenue']->value);
        $this->assertSame(2.0, (float) $metrics['ecommerce.orders']->value);
        $this->assertSame(75.0, (float) $metrics['ecommerce.aov']->value);
        $this->assertSame(5.0, (float) $metrics['ecommerce.refunds']->value);
        $this->assertSame('GBP', $result->snapshotPayload()['currency']);
    }

    public function test_collector_builds_a_daily_revenue_timeseries(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['has_more' => false, 'data' => [
                ['id' => 'ch_1', 'status' => 'succeeded', 'paid' => true, 'currency' => 'gbp', 'amount' => 10000, 'amount_refunded' => 0, 'created' => CarbonImmutable::parse('2026-08-01 10:00:00')->getTimestamp()],
                ['id' => 'ch_2', 'status' => 'succeeded', 'paid' => true, 'currency' => 'gbp', 'amount' => 5000, 'amount_refunded' => 0, 'created' => CarbonImmutable::parse('2026-08-03 10:00:00')->getTimestamp()],
            ]]),
        ]);

        $result = (new SalesCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-03'));

        $series = collect($result->snapshotPayload()['timeseries'])->keyBy('date');
        $this->assertCount(3, $series);
        $this->assertSame(100.0, $series['2026-08-01']['value']);
        $this->assertSame(0.0, $series['2026-08-02']['value']);
        $this->assertSame(50.0, $series['2026-08-03']['value']);
    }

    public function test_store_block_available_for_a_stripe_only_site(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'stripe',
            'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Store performance');
    }
}
