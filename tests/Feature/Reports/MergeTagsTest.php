<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MergeTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_tags_are_replaced_in_the_frozen_report_text(): void
    {
        Http::fake();

        $client = Client::factory()->create(['name' => 'Coastal Holidays', 'contact_name' => 'Sam']);
        $site = Site::factory()->for($client)->create(['name' => 'coastalholidays.net']);
        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create([
            'type' => 'text',
            'position' => 0,
            'heading' => 'A note for {{ client }}',
            'commentary' => 'Hi {{ contact }}, here is {{ site }} for {{ period }}. Left as-is: {{ unknown }}.',
        ]);

        app(ReportGenerator::class)->generate($report);

        $block = collect($report->latestRender->data)->firstWhere('type', 'text');

        $this->assertSame('A note for Coastal Holidays', $block['heading']);
        $this->assertStringContainsString('Hi Sam,', $block['commentary']);
        $this->assertStringContainsString('coastalholidays.net', $block['commentary']);
        $this->assertStringContainsString('Aug', $block['commentary']);
        $this->assertStringContainsString('2026', $block['commentary']);
        // Known tags are gone; an unknown tag is left exactly as written.
        $this->assertStringNotContainsString('{{ client }}', $block['commentary']);
        $this->assertStringContainsString('{{ unknown }}', $block['commentary']);
    }

    public function test_contact_falls_back_to_the_client_name(): void
    {
        Http::fake();

        $client = Client::factory()->create(['name' => 'Acme Ltd', 'contact_name' => null]);
        $site = Site::factory()->for($client)->create();
        $report = Report::factory()->for($site)->create();
        $report->blocks()->create(['type' => 'text', 'position' => 0, 'commentary' => 'Hi {{ contact }}.']);

        app(ReportGenerator::class)->generate($report);

        $block = collect($report->latestRender->data)->firstWhere('type', 'text');
        $this->assertSame('Hi Acme Ltd.', $block['commentary']);
    }
}
