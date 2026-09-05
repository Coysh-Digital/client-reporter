<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\DownloadTracker\Blocks\DownloadsBlock;
use App\Integrations\DownloadTracker\DownloadsCollector;
use App\Integrations\DownloadTracker\DownloadTrackerIntegration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\MetricReader;
use App\Reporting\Support\BlockContext;
use App\Support\Branding\BrandingResolver;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DownloadTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function connection(?Site $site = null): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'site_id' => ($site ?? Site::factory()->create())->id,
            'integration_key' => 'download_tracker',
            'name' => 'Download Tracker',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://craft.test'],
            'credentials' => ['secret' => 'shared-secret'],
        ]);
    }

    public function test_verify_succeeds_against_the_plugin(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'download-tracker', 'version' => '1.5.0'])]);

        $result = (new DownloadTrackerIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertSame('1.5.0', $result->meta['connector_version']);
    }

    public function test_it_signs_requests_to_the_plugin_route(): void
    {
        Http::fake(['craft.test/*' => Http::response(['ok' => true, 'connector' => 'download-tracker'])]);

        (new DownloadTrackerIntegration)->verify($this->connection());

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-CR-Signature')
            && str_contains($request->url(), '/download-tracker/v1/verify'));
    }

    public function test_collector_maps_downloads_metrics_and_snapshot(): void
    {
        Http::fake(['craft.test/*' => Http::response([
            'provider' => 'Download Tracker',
            'metrics' => ['downloads' => 420, 'files' => 12],
            'timeseries' => [['date' => '2026-08-01', 'value' => 15], ['date' => '2026-08-02', 'value' => 22]],
            'top_files' => [['label' => 'brochure.pdf', 'downloads' => 130]],
        ])]);

        $result = (new DownloadsCollector)->collect($this->connection(), new DateRange('2026-08-01', '2026-08-31'));

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(420.0, $metrics['downloads.total']->value, 0.01);
        $this->assertEqualsWithDelta(12.0, $metrics['downloads.files']->value, 0.01);

        $snapshot = $result->snapshotPayload();
        $this->assertSame('brochure.pdf', $snapshot['top_files'][0]['label']);
        $this->assertSame(['date' => '2026-08-02', 'value' => 22], $snapshot['timeseries'][1]);
    }

    public function test_it_is_registered_in_the_downloads_category_with_its_block(): void
    {
        $integration = app(IntegrationRegistry::class)->find('download_tracker');

        $this->assertNotNull($integration);
        $this->assertSame(IntegrationCategory::Downloads, $integration->manifest()->category);
        $this->assertContains(DownloadsBlock::class, $integration->reportBlocks());
    }

    public function test_block_resolves_tiles_top_files_and_trend(): void
    {
        $site = Site::factory()->create();
        $connection = $this->connection($site);
        $range = new DateRange('2026-08-01', '2026-08-31');

        Metric::query()->create([
            'site_integration_id' => $connection->id,
            'metric_key' => 'downloads.total',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'value' => 420, 'captured_at' => now(),
        ]);
        Metric::query()->create([
            'site_integration_id' => $connection->id,
            'metric_key' => 'downloads.files',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'value' => 12, 'captured_at' => now(),
        ]);
        MetricSnapshot::query()->create([
            'site_integration_id' => $connection->id,
            'collector_key' => 'downloads',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'granularity' => 'range',
            'payload' => [
                'top_files' => [['label' => 'brochure.pdf', 'downloads' => 130]],
                'timeseries' => [['date' => '2026-08-01', 'value' => 15]],
            ],
            'captured_at' => now(),
        ]);

        $report = Report::factory()->for($site)->create();
        $block = $report->blocks()->create(['type' => 'downloads.summary', 'position' => 0, 'heading' => 'Downloads']);
        $context = new BlockContext(
            $site,
            $block->fresh() ?? $block,
            $range,
            new DateRange('2026-07-01', '2026-07-31'),
            app(MetricReader::class),
            app(BrandingResolver::class)->forSite($site),
        );

        $data = (new DownloadsBlock)->resolve($context);

        $this->assertTrue($data['has_data']);
        $this->assertSame(420.0, $data['tiles'][0]['current']);
        $this->assertSame('brochure.pdf', $data['top_files'][0]['label']);
        $this->assertCount(1, $data['timeseries']);
    }
}
