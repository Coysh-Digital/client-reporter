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

class FormResponsesBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_responses_block_renders_wordpress_form_data(): void
    {
        Http::fake([
            'wp.test/wp-json/client-reporter/v1/forms*' => Http::response([
                'active' => true,
                'providers' => ['gravity'],
                'total' => 12,
                'forms' => [
                    ['name' => 'Contact', 'source' => 'Gravity Forms', 'submissions' => 9],
                    ['name' => 'Newsletter', 'source' => 'Ninja Forms', 'submissions' => 3],
                ],
                'timeseries' => [
                    ['date' => '2026-08-01', 'value' => 5],
                    ['date' => '2026-08-02', 'value' => 0],
                    ['date' => '2026-08-03', 'value' => 7],
                ],
            ]),
            // The generator also runs the site/updates/woocommerce collectors.
            'wp.test/*' => Http::response(['wordpress_version' => '6.6', 'active' => false]),
        ]);

        $site = Site::factory()->create();
        SiteIntegration::factory()->for($site)->create([
            'integration_key' => 'wordpress',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://wp.test'],
            'credentials' => ['secret' => 'shared-secret'],
        ]);

        $report = Report::factory()->for($site)->create(['range_start' => '2026-08-01', 'range_end' => '2026-08-31']);
        $report->blocks()->create(['type' => 'forms.responses', 'position' => 0]);

        app(ReportGenerator::class)->generate($report);

        $block = collect($report->latestRender->data)->firstWhere('type', 'forms.responses');
        $this->assertNotNull($block);
        $this->assertTrue($block['data']['has_data']);

        $responses = collect($block['data']['tiles'])->firstWhere('label', 'Responses');
        $this->assertEqualsWithDelta(12, $responses['current'], 0.01);

        $this->assertSame('Contact', $block['data']['forms'][0]['name']);
        $this->assertCount(3, $block['data']['timeseries']);
        // The busiest day (7) sits at/above 60% of the peak, so it bands as "busy".
        $this->assertSame('busy', collect($block['data']['status_days'])->firstWhere('date', '2026-08-03')['status']);
        $this->assertSame('none', collect($block['data']['status_days'])->firstWhere('date', '2026-08-02')['status']);
    }
}
