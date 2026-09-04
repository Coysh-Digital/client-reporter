<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\PageSpeed\PageSpeedCollector;
use App\Integrations\PageSpeed\PageSpeedIntegration;
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

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function fakePageSpeed(): void
    {
        Http::fake(['*pagespeedonline*' => Http::response([
            'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.92]], 'audits' => []],
            'loadingExperience' => [
                'overall_category' => 'FAST',
                'metrics' => [
                    'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2100, 'category' => 'FAST'],
                    'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 150],
                    'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 5],
                ],
            ],
        ])]);
    }

    public function test_pagespeed_is_registered_in_a_performance_category(): void
    {
        $keys = app(IntegrationRegistry::class)->keysInCategory(IntegrationCategory::Performance);
        $this->assertContains('pagespeed', $keys);
    }

    public function test_pagespeed_verify_and_collector(): void
    {
        $this->fakePageSpeed();
        $site = Site::factory()->create(['url' => 'https://example.test']);
        $connection = SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'pagespeed', 'status' => ConnectionStatus::Connected,
            'settings' => ['strategy' => 'mobile'], 'credentials' => [],
        ]);

        $this->assertTrue((new PageSpeedIntegration)->verify($connection)->ok);

        $result = (new PageSpeedCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $metrics = collect($result->metrics())->keyBy('key');

        $this->assertSame(92.0, (float) $metrics['performance.score']->value);
        $this->assertSame(2100.0, (float) $metrics['performance.lcp_ms']->value);
        $this->assertEqualsWithDelta(0.05, $metrics['performance.cls']->value, 0.001);
        $this->assertSame('field', $result->snapshotPayload()['source']);
    }

    public function test_core_web_vitals_block_available_for_a_performance_site(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'pagespeed', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create();

        Livewire::actingAs($manager)->test(Builder::class, ['report' => $report])
            ->assertSee('Core Web Vitals');
    }
}
