<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\ConnectionStatus;
use App\Models\Report;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The consolidated Forms & Leads section (forms.summary) shows lead figures and,
 * from the same provider, email-campaign metrics and a campaign table — the two
 * previously separate "Leads" and "Email campaigns" sections are now one.
 */
class LeadsSummaryBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forms_summary_block_renders_leads_and_campaigns(): void
    {
        Http::fake([
            'us21.api.mailchimp.com/3.0/lists/abc' => Http::response(['name' => 'Newsletter', 'stats' => ['member_count' => 500]]),
            'us21.api.mailchimp.com/3.0/lists/abc/growth-history*' => Http::response(['history' => []]),
            'us21.api.mailchimp.com/3.0/campaigns*' => Http::response(['campaigns' => [
                [
                    'settings' => ['title' => 'Summer sale'],
                    'send_time' => '2026-08-15T09:00:00+00:00',
                    'emails_sent' => 900,
                    'report_summary' => ['unique_opens' => 360, 'subscriber_clicks' => 54, 'open_rate' => 0.4, 'click_rate' => 0.06],
                ],
            ]]),
        ]);

        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'mailchimp',
            'status' => ConnectionStatus::Connected,
            'settings' => ['list_id' => 'abc'],
            'credentials' => ['api_key' => 'valid-us21'],
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'forms.summary', 'position' => 0]);

        app(ReportGenerator::class)->generate($report);

        $block = collect($report->latestRender->data)->firstWhere('type', 'forms.summary');
        $this->assertNotNull($block);
        $this->assertTrue($block['data']['has_data']);
        $this->assertSame('Mailchimp', $block['data']['provider']);

        // Audience metric from the leads side.
        $audience = collect($block['data']['metrics'])->firstWhere('label', 'Total audience');
        $this->assertEqualsWithDelta(500, $audience['current'], 0.01);

        // Campaign metrics and the campaign table from the email side, in one section.
        $sent = collect($block['data']['metrics'])->firstWhere('label', 'Campaigns sent');
        $this->assertEqualsWithDelta(1, $sent['current'], 0.01);
        $openRate = collect($block['data']['metrics'])->firstWhere('label', 'Open rate');
        $this->assertEqualsWithDelta(40.0, $openRate['current'], 0.01);
        $this->assertSame('Summer sale', $block['data']['campaigns'][0]['name']);
    }
}
