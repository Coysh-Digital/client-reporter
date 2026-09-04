<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\ReportTemplate;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OotbTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_out_of_the_box_templates(): void
    {
        $this->seed(ReportTemplateSeeder::class);

        $this->assertDatabaseHas('report_templates', ['name' => 'Website Care Report']);
        $this->assertDatabaseHas('report_templates', ['name' => 'Marketing Performance Report']);
        $this->assertDatabaseHas('report_templates', ['name' => 'Ecommerce Report']);
        $this->assertDatabaseHas('report_templates', ['name' => 'Full Digital Report']);
    }

    public function test_ai_enabled_sections_ship_with_the_toggle_on(): void
    {
        $this->seed(ReportTemplateSeeder::class);

        $marketing = ReportTemplate::query()->where('name', 'Marketing Performance Report')->firstOrFail();
        $traffic = collect($marketing->blocks)->firstWhere('type', 'analytics.site_traffic');

        $this->assertTrue($traffic['config']['ai_summary']);
    }

    public function test_seeding_twice_does_not_duplicate_or_overwrite(): void
    {
        $this->seed(ReportTemplateSeeder::class);

        $template = ReportTemplate::query()->where('name', 'Ecommerce Report')->firstOrFail();
        $template->update(['description' => 'Edited by the agency.']);

        $this->seed(ReportTemplateSeeder::class);

        $this->assertSame(4, ReportTemplate::query()->count());
        $this->assertSame('Edited by the agency.', $template->refresh()->description);
    }
}
