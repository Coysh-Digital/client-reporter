<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\ConnectionStatus;
use App\Livewire\Reports\Builder;
use App\Models\AiSetting;
use App\Models\Report;
use App\Models\ReportBlock;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BuilderAiTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): void
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
                'choices' => [['message' => ['content' => 'A previewed AI summary.']]],
            ]),
        ]);
    }

    private function report(): Report
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
        $report->blocks()->create([
            'type' => 'uptime.overview', 'position' => 0, 'heading' => 'Uptime',
            'config' => ['ai_summary' => true],
        ]);

        return $report;
    }

    public function test_preview_button_persists_the_generated_summary(): void
    {
        $this->fake();
        AiSetting::create(['enabled' => true, 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'credentials' => ['api_key' => 'sk-secret']]);

        $report = $this->report();
        // Collect metrics so the section has data to summarise.
        app(ReportGenerator::class)->generate($report);
        $block = $report->blocks()->where('type', 'uptime.overview')->firstOrFail();

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('generateAi', $block->id)
            ->assertSet('edits.'.$block->id.'.ai_summary', 'A previewed AI summary.')
            ->assertSet('aiError', '');

        $this->assertSame('A previewed AI summary.', ReportBlock::find($block->id)->ai_summary);
    }

    public function test_button_reports_an_error_when_ai_is_disabled(): void
    {
        $this->fake();
        $report = $this->report();
        $block = $report->blocks()->where('type', 'uptime.overview')->firstOrFail();

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Builder::class, ['report' => $report])
            ->call('generateAi', $block->id)
            ->assertSet('aiError', fn ($v): bool => $v !== '');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));
        $this->assertNull(ReportBlock::find($block->id)->ai_summary);
    }
}
