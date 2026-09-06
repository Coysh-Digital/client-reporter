<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\ReportTemplate;
use App\Reporting\BlockTypeRegistry;
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

    public function test_the_full_digital_report_includes_every_available_section(): void
    {
        $this->seed(ReportTemplateSeeder::class);

        $full = ReportTemplate::query()->where('name', 'Full Digital Report')->firstOrFail();
        $included = collect($full->blocks)->pluck('type')->all();

        $everyType = collect(app(BlockTypeRegistry::class)->grouped())
            ->flatten()
            ->map(fn ($type) => $type->type())
            ->all();

        foreach ($everyType as $type) {
            $this->assertContains($type, $included, "Full Digital Report is missing the {$type} section.");
        }
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

    public function test_the_migration_upgrades_an_existing_partial_full_digital_report(): void
    {
        // Simulate an install set up before the full version shipped by shrinking
        // the template the migration seeds into the baseline back to a short set.
        ReportTemplate::query()->where('name', 'Full Digital Report')->firstOrFail()->update([
            'description' => 'Old partial version.',
            'blocks' => [['type' => 'cover', 'heading' => 'Cover', 'config' => null]],
        ]);

        $migration = require database_path('migrations/2026_09_13_100000_upgrade_full_digital_report_template.php');
        $migration->up();

        // Still a single template, now carrying every available section.
        $this->assertSame(1, ReportTemplate::query()->where('name', 'Full Digital Report')->count());

        $included = collect(ReportTemplate::query()->where('name', 'Full Digital Report')->firstOrFail()->blocks)
            ->pluck('type')->all();
        $this->assertContains('billing.summary', $included);
        $this->assertContains('ecommerce.summary', $included);
        $this->assertContains('performance.summary', $included);
    }

    public function test_the_migration_creates_the_template_when_missing(): void
    {
        ReportTemplate::query()->delete();

        $migration = require database_path('migrations/2026_09_13_100000_upgrade_full_digital_report_template.php');
        $migration->up();

        $this->assertDatabaseHas('report_templates', ['name' => 'Full Digital Report']);
        $this->assertSame(1, ReportTemplate::query()->where('name', 'Full Digital Report')->count());
    }
}
