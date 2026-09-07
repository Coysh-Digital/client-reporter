<?php

declare(strict_types=1);

use App\Models\ReportTemplate;
use App\Reporting\BlockTypeRegistry;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the new "Email campaigns" section to the "Full Digital Report" template
 * on installs that already have it, re-syncing from the same seeder definition
 * so there is one source of truth. Fresh installs get it from the seeder.
 *
 * Sections a site has no data for are skipped when a report is generated, so a
 * template listing every section stays safe for every site.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = ReportTemplate::query()->where('name', 'Full Digital Report')->first();
        if ($template === null) {
            return;
        }

        $definition = ReportTemplateSeeder::definition('Full Digital Report');
        $template->update([
            'blocks' => ReportTemplateSeeder::buildBlocks(app(BlockTypeRegistry::class), $definition['sections']),
        ]);
    }

    public function down(): void
    {
        $template = ReportTemplate::query()->where('name', 'Full Digital Report')->first();
        if ($template === null) {
            return;
        }

        $definition = ReportTemplateSeeder::definition('Full Digital Report');
        $sections = array_values(array_filter(
            $definition['sections'],
            fn (array $section): bool => ($section[0] ?? '') !== 'email.campaigns',
        ));

        $template->update([
            'blocks' => ReportTemplateSeeder::buildBlocks(app(BlockTypeRegistry::class), $sections),
        ]);
    }
};
