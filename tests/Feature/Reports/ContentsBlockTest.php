<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\ReportGenerator;
use App\Support\ReportIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The report's Contents section lists every other visible block with an icon
 * and a jump link to its anchor — the same anchor id the block's own <section>
 * carries, so the link actually resolves in the rendered document.
 */
class ContentsBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_contents_block_is_registered(): void
    {
        $this->assertNotNull(app(BlockTypeRegistry::class)->find('contents'));
    }

    public function test_report_icons_returns_a_data_uri_svg_image(): void
    {
        // dompdf won't render an inline <svg>, but it renders SVG through its
        // image pipeline, so icons are monoline vectors embedded as a data-URI
        // <img> (the same technique the report charts use).
        $html = ReportIcons::html('chart');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('#8a6a2c', $this->decodeIcon($html));
    }

    public function test_report_icons_accepts_a_custom_colour(): void
    {
        $svg = $this->decodeIcon(ReportIcons::html('chart', '#33406b'));

        $this->assertStringContainsString('stroke="#33406b"', $svg);
        $this->assertStringNotContainsString('#8a6a2c', $svg);
    }

    private function decodeIcon(string $html): string
    {
        preg_match('/base64,([^"]+)/', $html, $m);

        return base64_decode($m[1] ?? '');
    }

    public function test_report_icons_falls_back_to_document_for_an_unknown_key(): void
    {
        $default = ReportIcons::html('document');
        $unknown = ReportIcons::html('not-a-real-key');

        $this->assertSame($default, $unknown);
    }

    public function test_contents_block_lists_sibling_blocks_with_working_anchors(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response(['stat' => 'ok', 'monitors' => [[
            'id' => 1, 'friendly_name' => 'Site', 'status' => 2,
            'average_response_time' => '210', 'custom_uptime_ranges' => '99.9000',
        ]]])]);

        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'uptimerobot', 'status' => ConnectionStatus::Connected,
        ]);
        $report = Report::factory()->for($site)->create([
            'range_start' => '2026-08-01', 'range_end' => '2026-08-31',
        ]);

        $blocks = [];
        foreach (['cover', 'contents', 'uptime.summary', 'closing'] as $i => $type) {
            $blocks[$type] = $report->blocks()->create(['type' => $type, 'position' => $i, 'heading' => null]);
        }

        app(ReportGenerator::class)->generate($report);
        $report->refresh();

        $contents = collect($report->latestRender->data)->firstWhere('type', 'contents');
        $items = $contents['data']['items'];

        // Only the uptime block is listed — cover, contents and closing never
        // list themselves.
        $this->assertCount(1, $items);
        $this->assertSame('pulse', $items[0]['icon']);
        $this->assertSame('block-'.$blocks['uptime.summary']->id, $items[0]['anchor']);

        $admin = User::factory()->administrator()->create();
        $html = $this->actingAs($admin)->get(route('reports.preview', $report))->getContent();

        // The link the Contents block prints resolves to a real anchor on the
        // uptime section's own <section id="...">.
        $this->assertStringContainsString('href="#'.$items[0]['anchor'].'"', $html);
        $this->assertStringContainsString('id="'.$items[0]['anchor'].'"', $html);
    }
}
