<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Livewire\Integrations\SitePanel;
use App\Livewire\Sites\Show;
use App\Models\CollectorRun;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Support\DateRange;
use App\Support\MetricLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SitePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_label_humanises_dotted_keys(): void
    {
        $this->assertSame('Visitors', MetricLabel::for('analytics.visitors'));
        $this->assertSame('Bounce Rate', MetricLabel::for('analytics.bounce_rate'));
        $this->assertSame('Percentage', MetricLabel::for('uptime.percentage'));
    }

    public function test_a_row_shows_state_timing_and_the_category_headline_metric(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create([
            'site_id' => $site->id,
            'last_collected_at' => now()->subHours(2),
            'last_attempted_at' => now()->subHours(2),
        ]);

        $month = DateRange::thisMonth();
        foreach (['uptime.percentage' => [99.95, '%'], 'uptime.response_time_ms' => [412.0, 'ms'], 'uptime.incidents' => [3.0, null]] as $key => [$value, $unit]) {
            Metric::query()->create([
                'site_integration_id' => $connection->id,
                'metric_key' => $key,
                'period_start' => $month->start->toDateString(),
                'period_end' => $month->end->toDateString(),
                'value' => $value,
                'unit' => $unit,
                'captured_at' => now(),
            ]);
        }

        $component = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->assertSee('Connected')
            ->assertSee('Last collected 2 hours ago')
            ->assertSee('next due');

        // UptimeRobot is a monitoring integration, so uptime wins over the larger response time.
        $headline = $component->viewData('headlines')[$connection->id];
        $this->assertSame('Percentage', $headline['label']);
        $this->assertSame('99.95%', $headline['value']);
        $this->assertSame($month->start->format('M Y'), $headline['period']);
    }

    public function test_the_headline_falls_back_to_last_month_then_to_the_largest_value(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);

        $last = DateRange::lastMonth();
        foreach (['analytics.visitors' => 4000.0, 'analytics.pageviews' => 9000.0] as $key => $value) {
            Metric::query()->create([
                'site_integration_id' => $connection->id,
                'metric_key' => $key,
                'period_start' => $last->start->toDateString(),
                'period_end' => $last->end->toDateString(),
                'value' => $value,
                'unit' => null,
                'captured_at' => now(),
            ]);
        }

        $headline = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->viewData('headlines')[$connection->id];

        // Monitoring's default (uptime) was not collected, so the largest value stands in.
        $this->assertSame('Pageviews', $headline['label']);
        $this->assertSame('9,000', $headline['value']);
        $this->assertSame($last->start->format('M Y'), $headline['period']);
    }

    public function test_a_timeseries_snapshot_gives_a_sparkline_and_a_daily_chart(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);

        $series = [];
        for ($day = 1; $day <= 40; $day++) {
            $series[] = ['date' => sprintf('2026-08-%02d', min($day, 31)), 'value' => 100 + $day];
        }

        MetricSnapshot::query()->create([
            'site_integration_id' => $connection->id,
            'collector_key' => 'summary',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'granularity' => 'range',
            'payload' => ['timeseries' => $series],
            'captured_at' => now(),
        ]);

        $component = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site]);
        $trend = $component->viewData('trends')[$connection->id];

        $this->assertSame('Visitors per day', $trend['label']);
        $this->assertCount(40, $trend['data']);
        $this->assertCount(30, $trend['spark'], 'The sparkline keeps the most recent 30 points.');
        $this->assertSame(140.0, end($trend['spark']));
        $component->assertSee('crLineChart', false)->assertSeeHtml('<svg');
    }

    public function test_a_queued_collection_shows_as_syncing_and_polls(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->create([
            'site_id' => $site->id,
            'collection_queued_at' => now()->subMinute(),
            'last_attempted_at' => now()->subHour(),
        ]);

        Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->assertSee('Syncing')
            ->assertSee('collecting now')
            ->assertSeeHtml('wire:poll.10s');
    }

    public function test_an_abandoned_run_is_not_reported_as_syncing(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);
        CollectorRun::query()->create([
            'site_integration_id' => $connection->id,
            'collector_key' => 'monitors',
            'status' => 'running',
            'started_at' => now()->subHour(),
        ]);

        Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->assertDontSee('Syncing')
            ->assertSee('Connected');
    }

    public function test_the_panel_uses_a_bounded_number_of_queries(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        foreach (['uptimerobot', 'uptime_kuma', 'plausible', 'fathom', 'matomo', 'umami'] as $i => $key) {
            $connection = SiteIntegration::factory()->create(['site_id' => $site->id, 'integration_key' => $key, 'name' => "Connection {$i}"]);
            Metric::query()->create([
                'site_integration_id' => $connection->id,
                'metric_key' => 'uptime.percentage',
                'period_start' => DateRange::thisMonth()->start->toDateString(),
                'period_end' => DateRange::thisMonth()->end->toDateString(),
                'value' => 99.0,
                'unit' => '%',
                'captured_at' => now(),
            ]);
            MetricSnapshot::query()->create([
                'site_integration_id' => $connection->id,
                'collector_key' => 'monitors',
                'period_start' => DateRange::thisMonth()->start->toDateString(),
                'period_end' => DateRange::thisMonth()->end->toDateString(),
                'granularity' => 'range',
                'payload' => ['timeseries' => [['date' => '2026-09-01', 'value' => 1], ['date' => '2026-09-02', 'value' => 2]]],
                'captured_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site]);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $count, "Expected a handful of bulk queries, ran {$count}.");
    }

    public function test_the_site_page_offers_only_integrations_that_can_still_be_connected(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        // Already connected on this site.
        SiteIntegration::factory()->for($site)->create(['integration_key' => 'uptimerobot', 'status' => ConnectionStatus::Connected]);
        // Connected once for the whole workspace.
        WorkspaceIntegration::query()->create([
            'integration_key' => 'uptime_kuma',
            'name' => 'Uptime Kuma (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'k'],
        ]);

        $available = Livewire::actingAs($manager)->test(Show::class, ['site' => $site])->viewData('available');
        $keys = collect($available)->pluck('items')->flatten(1)->map(fn ($i) => $i->key())->all();

        $this->assertNotContains('uptimerobot', $keys, 'A service already connected here should be hidden.');
        $this->assertNotContains('uptime_kuma', $keys, 'A workspace-connected service should be hidden.');
        $this->assertNotContains('freeagent', $keys, 'A workspace-only service should never appear here.');
        $this->assertContains('plausible', $keys, 'Unconnected services should still be offered.');
    }

    public function test_the_panel_has_no_headline_or_trend_before_any_collection(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);

        $component = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site]);

        $this->assertArrayNotHasKey($connection->id, $component->viewData('headlines'));
        $this->assertArrayNotHasKey($connection->id, $component->viewData('trends'));
        $component->assertSee('Never collected');
    }
}
