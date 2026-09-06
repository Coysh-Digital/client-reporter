<?php

declare(strict_types=1);

use App\Models\ReportTemplate;
use App\Reporting\BlockTypeRegistry;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Brings the "Full Digital Report" template up to every available section for
 * installs that already have the earlier, shorter version (or none at all).
 *
 * Fresh installs get the full version straight from the seeder; that seeder is
 * create-only (it never overwrites), so this migration is what upgrades an
 * existing install to the complete template. It builds the blocks from the same
 * definition the seeder uses, so there is one source of truth.
 *
 * Sections a site has no data for are skipped when a report is generated, so a
 * template listing every section is safe for every site.
 */
return new class extends Migration
{
    /** Sections the template shipped with before this migration, for a clean rollback. */
    private const PREVIOUS_SECTIONS = [
        ['cover', 'Cover'],
        ['contents', 'Contents'],
        ['text', 'Introduction'],
        ['website-overview', 'Website overview'],
        ['analytics.site_traffic', 'Site traffic', true],
        ['search.summary', 'Search performance', true],
        ['uptime.overview', 'Uptime & performance', true],
        ['forms.summary', 'Leads & signups', true],
        ['ai.summary', 'Month in review'],
        ['closing', 'Thank you'],
    ];

    public function up(): void
    {
        $registry = app(BlockTypeRegistry::class);
        $definition = ReportTemplateSeeder::definition('Full Digital Report');

        ReportTemplate::query()->updateOrCreate(
            ['name' => 'Full Digital Report'],
            [
                'description' => $definition['description'],
                'blocks' => ReportTemplateSeeder::buildBlocks($registry, $definition['sections']),
            ],
        );
    }

    public function down(): void
    {
        $template = ReportTemplate::query()->where('name', 'Full Digital Report')->first();
        if ($template === null) {
            return;
        }

        // Go through the model so the JSON cast encodes the blocks.
        $template->update([
            'description' => 'Everything in one report: overview, traffic, search, uptime & performance and leads, each with an AI summary.',
            'blocks' => ReportTemplateSeeder::buildBlocks(app(BlockTypeRegistry::class), self::PREVIOUS_SECTIONS),
        ]);
    }
};
