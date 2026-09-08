<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Report;
use App\Models\Site;
use App\Reporting\Blocks\BillingBlock;
use App\Reporting\Blocks\TextBlock;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HideEmptySectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_section_is_dropped_and_pruned_from_the_contents(): void
    {
        Http::fake();

        [$report, $blocks] = $this->reportWithBlocks();

        app(ReportGenerator::class)->generate($report);
        $data = $report->latestRender->data;

        // The billing section resolved empty (no invoices) and is left out.
        $this->assertArrayNotHasKey($blocks['billing'], $data);
        // The text section always renders.
        $this->assertArrayHasKey($blocks['text'], $data);

        // The table of contents no longer links to the dropped section.
        $contents = collect($data)->firstWhere('type', 'contents');
        $anchors = collect($contents['data']['items'])->pluck('anchor')->all();
        $this->assertNotContains('block-'.$blocks['billing'], $anchors);
        $this->assertContains('block-'.$blocks['text'], $anchors);
    }

    public function test_a_section_with_data_is_kept(): void
    {
        Http::fake();

        [$report, $blocks] = $this->reportWithBlocks();
        Invoice::factory()->for($report->site->client)->create([
            'status' => InvoiceStatus::Sent,
            'issued_at' => '2026-08-10',
        ]);

        app(ReportGenerator::class)->generate($report);
        $data = $report->latestRender->data;

        $this->assertArrayHasKey($blocks['billing'], $data);
        $contents = collect($data)->firstWhere('type', 'contents');
        $anchors = collect($contents['data']['items'])->pluck('anchor')->all();
        $this->assertContains('block-'.$blocks['billing'], $anchors);
    }

    public function test_turning_the_toggle_off_keeps_an_empty_section(): void
    {
        Http::fake();

        [$report, $blocks] = $this->reportWithBlocks(hideBillingWhenEmpty: false);

        app(ReportGenerator::class)->generate($report);
        $data = $report->latestRender->data;

        $this->assertArrayHasKey($blocks['billing'], $data);
        $billing = collect($data)->firstWhere('type', 'billing.summary');
        $this->assertFalse($billing['data']['has_data']);
    }

    public function test_empty_capable_blocks_expose_the_toggle_defaulting_on(): void
    {
        $billing = new BillingBlock;
        $keys = array_column($billing->builderOptions(), 'key');
        $this->assertContains('hide_when_empty', $keys);
        $this->assertTrue($billing->defaultConfig()['hide_when_empty']);

        // A block that always renders never gets the toggle.
        $text = new TextBlock;
        $this->assertNotContains('hide_when_empty', array_column($text->builderOptions(), 'key'));
    }

    /**
     * A report with a contents list, an (empty) billing section and a text
     * section. Returns the report and a map of section name => block id.
     *
     * @return array{0: Report, 1: array<string, int>}
     */
    private function reportWithBlocks(bool $hideBillingWhenEmpty = true): array
    {
        $client = Client::factory()->create();
        $site = Site::factory()->for($client)->create();
        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);

        $contents = $report->blocks()->create(['type' => 'contents', 'position' => 0]);
        $billing = $report->blocks()->create([
            'type' => 'billing.summary',
            'position' => 1,
            'config' => ['hide_when_empty' => $hideBillingWhenEmpty],
        ]);
        $text = $report->blocks()->create(['type' => 'text', 'position' => 2, 'commentary' => 'Hello']);

        return [$report, ['contents' => $contents->id, 'billing' => $billing->id, 'text' => $text->id]];
    }
}
