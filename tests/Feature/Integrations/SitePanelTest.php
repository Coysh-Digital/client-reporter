<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Livewire\Integrations\SitePanel;
use App\Models\Metric;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Support\MetricLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_panel_shows_last_collected_and_metric_insights(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create([
            'site_id' => $site->id,
            'last_collected_at' => now()->subHours(2),
        ]);

        foreach (['2026-08-01' => 4000.0, '2026-09-01' => 5200.0] as $start => $visitors) {
            Metric::query()->create([
                'site_integration_id' => $connection->id,
                'metric_key' => 'analytics.visitors',
                'period_start' => $start,
                'period_end' => $start,
                'value' => $visitors,
                'unit' => null,
                'captured_at' => now(),
            ]);
        }
        Metric::query()->create([
            'site_integration_id' => $connection->id,
            'metric_key' => 'analytics.bounce_rate',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-01',
            'value' => 42.5,
            'unit' => '%',
            'captured_at' => now(),
        ]);

        $component = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])
            ->assertSee('Last collected')
            ->assertSee('Visitors')
            ->assertSee('Bounce Rate');

        $insight = $component->viewData('insights')[$connection->id];

        // Headline is the largest latest value (visitors), charted across periods.
        $this->assertSame('Visitors', $insight['chart']['label']);
        $this->assertSame(['Aug 2026', 'Sep 2026'], $insight['chart']['labels']);
        $this->assertSame([4000.0, 5200.0], $insight['chart']['data']);
    }

    public function test_connect_list_hides_connected_workspace_and_workspace_only_services(): void
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

        $available = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site])->viewData('available');
        $keys = collect($available)->flatten(1)->map(fn ($i) => $i->key())->all();

        $this->assertNotContains('uptimerobot', $keys, 'A service already connected here should be hidden.');
        $this->assertNotContains('uptime_kuma', $keys, 'A workspace-connected service should be hidden.');
        $this->assertNotContains('freeagent', $keys, 'A workspace-only service should never appear here.');
        $this->assertContains('plausible', $keys, 'Unconnected services should still be offered.');
    }

    public function test_panel_has_no_insight_before_any_collection(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        $connection = SiteIntegration::factory()->create(['site_id' => $site->id]);

        $component = Livewire::actingAs($manager)->test(SitePanel::class, ['site' => $site]);

        $this->assertNull($component->viewData('insights')[$connection->id]);
    }
}
