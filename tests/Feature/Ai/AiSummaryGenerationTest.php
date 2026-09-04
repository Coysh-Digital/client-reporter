<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\ConnectionStatus;
use App\Models\AiSetting;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\ReportDocument;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSummaryGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUptime(): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response([
                'stat' => 'ok',
                'monitors' => [[
                    'id' => 1, 'friendly_name' => 'Site', 'status' => 2,
                    'average_response_time' => '210', 'custom_uptime_ranges' => '99.9000',
                ]],
            ]),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'A concise AI summary.']]],
            ]),
        ]);
    }

    private function enableAi(): void
    {
        AiSetting::create([
            'enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'credentials' => ['api_key' => 'sk-secret'],
        ]);
    }

    private function reportWithAiBlocks(): Report
    {
        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'uptimerobot',
            'status' => ConnectionStatus::Connected,
        ]);

        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01',
            'range_end' => '2026-08-31',
        ]);

        $report->blocks()->create(['type' => 'cover', 'position' => 0, 'heading' => 'Cover']);
        $report->blocks()->create([
            'type' => 'uptime.overview', 'position' => 1, 'heading' => 'Uptime',
            'config' => ['ai_summary' => true],
        ]);
        $report->blocks()->create(['type' => 'ai.summary', 'position' => 2, 'heading' => 'Month in review']);

        return $report;
    }

    public function test_generation_injects_section_and_roundup_ai_text_into_the_render(): void
    {
        $this->fakeUptime();
        $this->enableAi();
        $report = $this->reportWithAiBlocks();

        app(ReportGenerator::class)->generate($report);

        $render = $report->refresh()->latestRender;
        $overview = collect($render->data)->firstWhere('type', 'uptime.overview');
        $roundup = collect($render->data)->firstWhere('type', 'ai.summary');

        $this->assertSame('A concise AI summary.', $overview['data']['ai_summary']);
        $this->assertSame('A concise AI summary.', $roundup['data']['ai_summary']);

        // The frozen text renders into the document (through the ai-summary partial).
        $document = app(ReportDocument::class)->fromRender($render);
        $html = view('reports.document', $document)->render();
        $this->assertStringContainsString('ai-summary-label', $html);
        $this->assertStringContainsString('A concise AI summary.', $html);
    }

    public function test_disabled_ai_produces_no_calls_and_no_summary(): void
    {
        $this->fakeUptime();
        // No AiSetting created → disabled by default.
        $report = $this->reportWithAiBlocks();

        app(ReportGenerator::class)->generate($report);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));

        $render = $report->refresh()->latestRender;
        $overview = collect($render->data)->firstWhere('type', 'uptime.overview');
        $this->assertArrayNotHasKey('ai_summary', $overview['data']);
    }

    public function test_provider_failure_degrades_gracefully(): void
    {
        Http::fake([
            'api.uptimerobot.com/*' => Http::response([
                'stat' => 'ok',
                'monitors' => [[
                    'id' => 1, 'friendly_name' => 'Site', 'status' => 2,
                    'average_response_time' => '210', 'custom_uptime_ranges' => '99.9000',
                ]],
            ]),
            'api.openai.com/*' => Http::response([], 500),
        ]);
        $this->enableAi();
        $report = $this->reportWithAiBlocks();

        app(ReportGenerator::class)->generate($report);

        $report->refresh();
        $this->assertSame('final', $report->status);

        $render = $report->latestRender;
        $overview = collect($render->data)->firstWhere('type', 'uptime.overview');
        // Deterministic data survives; the AI summary is simply absent.
        $this->assertTrue($overview['data']['has_data']);
        $this->assertArrayNotHasKey('ai_summary', $overview['data']);
    }

    public function test_live_preview_makes_no_ai_calls(): void
    {
        $this->fakeUptime();
        $this->enableAi();
        $report = $this->reportWithAiBlocks();

        app(ReportDocument::class)->live($report->load('blocks'));

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));
    }
}
