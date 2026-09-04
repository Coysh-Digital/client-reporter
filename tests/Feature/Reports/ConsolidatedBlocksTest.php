<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\Blocks\Analytics\SiteTrafficBlock;
use App\Reporting\Blocks\Uptime\UptimeOverviewBlock;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\MetricReader;
use App\Reporting\ReportComposer;
use App\Reporting\Support\BlockContext;
use App\Support\Branding\BrandingResolver;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedBlocksTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $range;

    private DateRange $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->range = new DateRange('2026-08-01', '2026-08-31');
        $this->previous = new DateRange('2026-07-01', '2026-07-31');
    }

    private function metric(SiteIntegration $c, string $key, float $value, DateRange $range, ?string $unit = null): void
    {
        Metric::query()->create([
            'site_integration_id' => $c->id,
            'metric_key' => $key,
            'period_start' => $range->start->toDateString(),
            'period_end' => $range->end->toDateString(),
            'value' => $value,
            'unit' => $unit,
            'captured_at' => now(),
        ]);
    }

    private function snapshot(SiteIntegration $c, string $collector, array $payload): void
    {
        MetricSnapshot::query()->create([
            'site_integration_id' => $c->id,
            'collector_key' => $collector,
            'period_start' => $this->range->start->toDateString(),
            'period_end' => $this->range->end->toDateString(),
            'granularity' => 'range',
            'payload' => $payload,
            'captured_at' => now(),
        ]);
    }

    private function context(Site $site, array $config = []): BlockContext
    {
        $report = Report::factory()->for($site)->create();
        $block = $report->blocks()->create(['type' => 'x', 'position' => 0, 'heading' => 'x', 'config' => $config ?: null]);

        return new BlockContext(
            $site,
            $block->fresh() ?? $block,
            $this->range,
            $this->previous,
            app(MetricReader::class),
            app(BrandingResolver::class)->forSite($site),
        );
    }

    public function test_site_traffic_block_consolidates_analytics_data(): void
    {
        $site = Site::factory()->create();
        $c = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'plausible',
            'status' => ConnectionStatus::Connected,
        ]);

        $this->metric($c, 'analytics.visitors', 14310, $this->range);
        $this->metric($c, 'analytics.visits', 17771, $this->range);
        $this->metric($c, 'analytics.pageviews', 40893, $this->range);
        $this->metric($c, 'analytics.visit_duration', 152, $this->range, 'seconds');
        $this->metric($c, 'analytics.bounce_rate', 46.7, $this->range, '%');
        $this->metric($c, 'analytics.visitors', 14557, $this->previous); // prior month, for delta

        $this->snapshot($c, 'summary', [
            'provider' => 'Plausible',
            'timeseries' => [['date' => '2026-08-01', 'value' => 320], ['date' => '2026-08-02', 'value' => 410]],
            'top_pages' => [['label' => '/', 'pageviews' => 9733, 'visitors' => 8000]],
            'sources' => [['label' => 'Google.com', 'visitors' => 6047]],
            'countries' => [['label' => 'United States', 'visitors' => 9707]],
            'devices' => [['label' => 'Mobile', 'visitors' => 8062]],
            'events' => [],
        ]);

        $data = (new SiteTrafficBlock)->resolve($this->context($site, ['compare' => true]));

        $this->assertTrue($data['has_data']);
        $this->assertSame('Plausible', $data['provider']);
        $this->assertSame(14310.0, $data['tiles'][0]['current']);
        $this->assertSame(14557.0, $data['tiles'][0]['previous']);
        $this->assertSame(46.7, $data['bounce_rate']);
        $this->assertCount(1, $data['top_pages']);
        $this->assertStringContainsString('14,310 visitors', $data['summary']);
        $this->assertStringContainsString('Google.com was the largest source', $data['summary']);
    }

    public function test_uptime_overview_block_consolidates_monitoring_and_performance(): void
    {
        $site = Site::factory()->create();
        $mon = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'uptime_kuma',
            'status' => ConnectionStatus::Connected,
        ]);
        $perf = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'pagespeed',
            'status' => ConnectionStatus::Connected,
        ]);

        $this->metric($mon, 'uptime.percentage', 99.92, $this->range, '%');
        $this->metric($mon, 'uptime.response_time_ms', 323, $this->range, 'ms');
        $this->metric($mon, 'uptime.incidents', 0, $this->range);
        $this->metric($mon, 'uptime.monitors', 1, $this->range);
        $this->metric($mon, 'uptime.cert_alerts', 0, $this->range);
        $this->metric($perf, 'performance.score', 91, $this->range);
        $this->metric($perf, 'performance.accessibility', 98, $this->range);
        $this->metric($perf, 'performance.best_practices', 96, $this->range);
        $this->metric($perf, 'performance.seo', 100, $this->range);

        $this->snapshot($mon, 'monitors', [
            'timeseries' => [
                ['date' => '2026-08-01', 'value' => 100.0],
                ['date' => '2026-08-02', 'value' => 99.6],
                ['date' => '2026-08-03', 'value' => 98.2],
                ['date' => '2026-08-04', 'value' => 0.0],
            ],
            'incidents' => [],
            'monitors' => [['name' => 'stroudcf.org', 'status' => 'up']],
        ]);

        $data = (new UptimeOverviewBlock)->resolve($this->context($site, ['compare' => true]));

        $this->assertTrue($data['has_data']);
        $this->assertSame(99.92, $data['tiles'][0]['current']);
        $this->assertSame('Cert alerts', $data['tiles'][4]['label']);
        $this->assertSame(0.0, $data['tiles'][4]['current']);

        $this->assertCount(4, $data['lighthouse']);
        $this->assertSame(['label' => 'Performance', 'score' => 91, 'rating' => 'good'], $data['lighthouse'][0]);
        $this->assertSame('SEO', $data['lighthouse'][3]['label']);
        $this->assertStringContainsString('Lighthouse performance sits at 91', $data['summary']);

        $statuses = array_column($data['status_days'], 'status');
        $this->assertSame(['healthy', 'partial', 'below', 'none'], $statuses);
    }

    public function test_site_traffic_view_renders(): void
    {
        $html = view('reports.blocks.analytics.site_traffic', [
            'data' => [
                'has_data' => true,
                'provider' => 'Plausible',
                'summary' => '14,310 visitors this period.',
                'tiles' => [
                    ['label' => 'Visitors', 'fmt' => 'number', 'goodUp' => true, 'current' => 14310.0, 'previous' => 14557.0],
                ],
                'bounce_rate' => 46.7,
                'timeseries' => [['date' => '2026-08-01', 'value' => 320]],
                'top_pages' => [['label' => '/', 'pageviews' => 9733]],
                'sources' => [['label' => 'Google.com', 'visitors' => 6047]],
                'countries' => [['label' => 'United States', 'visitors' => 9707]],
                'devices' => [['label' => 'Mobile', 'visitors' => 8062]],
                'events' => [],
            ],
            'heading' => 'Site traffic',
            'commentary' => null,
            'icon' => 'chart',
            'branding' => (object) ['primaryColor' => '#33406b'],
        ])->render();

        $this->assertStringContainsString('Site traffic', $html);
        $this->assertStringContainsString('14,310', $html);
        $this->assertStringContainsString('Avg bounce rate 46.7%', $html);
        $this->assertStringContainsString('Top pages', $html);
        $this->assertStringContainsString('No custom events recorded', $html);
        // The visitors trend is a real SVG line chart embedded as a data-URI image.
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
    }

    public function test_uptime_overview_view_renders(): void
    {
        $html = view('reports.blocks.uptime.overview', [
            'data' => [
                'has_data' => true,
                'summary' => 'The site held 99.92% uptime.',
                'tiles' => [
                    ['label' => 'Avg uptime', 'fmt' => 'uptime', 'goodUp' => true, 'current' => 99.92, 'previous' => 99.92],
                ],
                'timeseries' => [['date' => '2026-08-01', 'value' => 100.0]],
                'status_days' => [['date' => '2026-08-01', 'status' => 'healthy'], ['date' => '2026-08-02', 'status' => 'below']],
                'incidents' => [],
                'lighthouse' => [
                    ['label' => 'Performance', 'score' => 91, 'rating' => 'good'],
                    ['label' => 'SEO', 'score' => 100, 'rating' => 'good'],
                ],
            ],
            'heading' => 'Uptime & performance',
            'commentary' => null,
            'icon' => 'pulse',
        ])->render();

        $this->assertStringContainsString('Uptime &amp; performance', $html);
        $this->assertStringContainsString('Daily uptime', $html);
        $this->assertStringContainsString('Below 99.5%', $html);
        $this->assertStringContainsString('91', $html);
        $this->assertStringContainsString('No outages detected', $html);
    }

    public function test_blocks_are_registered_and_in_the_default_template(): void
    {
        $registry = app(BlockTypeRegistry::class);
        $this->assertTrue($registry->has('analytics.site_traffic'));
        $this->assertTrue($registry->has('uptime.overview'));

        $types = array_column(ReportComposer::DEFAULT_BLOCKS, 'type');
        $this->assertContains('analytics.site_traffic', $types);
        $this->assertContains('uptime.overview', $types);
    }
}
