<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Reporting\ReportEmailContent;
use App\Support\Branding\BrandingResolver;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportEmailContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_and_body_cascade_site_over_global_over_default_with_merge_tags(): void
    {
        $client = Client::factory()->create(['name' => 'Coastal', 'contact_name' => null]);
        $site = Site::factory()->for($client)->create(['name' => 'coastal.net']);
        $report = Report::factory()->for($site)->create(['title' => 'Monthly report', 'range_start' => '2026-08-01', 'range_end' => '2026-08-31']);

        $content = app(ReportEmailContent::class);
        $settings = app(Settings::class);
        $branding = app(BrandingResolver::class)->forSite($site);

        // Nothing configured: subject falls back to the report title, body to null.
        $this->assertSame('Monthly report', $content->subject($report, $branding));
        $this->assertNull($content->body($report, $branding));

        // Workspace defaults apply, with merge tags filled in.
        $settings->set('report.email_subject', '{{ client }} — your report');
        $settings->set('report.email_body', 'Hi {{ contact }}, here is {{ site }}.');
        $this->assertSame('Coastal — your report', $content->subject($report, $branding));
        // contact falls back to the client name when no contact name is set.
        $this->assertSame('Hi Coastal, here is coastal.net.', $content->body($report, $branding));

        // A per-site override beats the workspace default.
        $site->update(['email_subject' => 'Your {{ period }} update']);
        $this->assertStringContainsString('Aug', $content->subject($report->fresh(), $branding));
    }
}
