<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ReportTemplate;
use App\Reporting\BlockTypeRegistry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the out-of-the-box report templates: ready-made sets of the common
 * sections in a logical order (cover, intro, the data sections, an AI "month in
 * review" roundup, and a closing note), one per common kind of engagement.
 *
 * Each section is [type, heading, ai?]; a truthy `ai` turns on that section's
 * AI summary where the block supports one, so the templates ship AI-ready and
 * simply stay quiet until a workspace configures a provider.
 *
 * Idempotent (firstOrCreate on name), so it is safe to run on every install and
 * again after an update without duplicating or overwriting edited templates.
 */
class ReportTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<string, array{description: string, sections: array<int, array{0: string, 1: string, 2?: bool}>}>
     */
    private const TEMPLATES = [
        'Website Care Report' => [
            'description' => 'Cover, introduction, website overview, uptime & performance and a closing note — for hosting and maintenance clients.',
            'sections' => [
                ['cover', 'Cover'],
                ['contents', 'Contents'],
                ['text', 'Introduction'],
                ['website-overview', 'Website overview'],
                ['uptime.overview', 'Uptime & performance', true],
                ['ai.summary', 'Month in review'],
                ['closing', 'Thank you'],
            ],
        ],
        'Marketing Performance Report' => [
            'description' => 'Traffic, search performance and leads with AI summaries — for SEO and marketing clients.',
            'sections' => [
                ['cover', 'Cover'],
                ['contents', 'Contents'],
                ['text', 'Introduction'],
                ['analytics.site_traffic', 'Site traffic', true],
                ['search.summary', 'Search performance', true],
                ['forms.summary', 'Leads & signups', true],
                ['ai.summary', 'Month in review'],
                ['closing', 'Thank you'],
            ],
        ],
        'Ecommerce Report' => [
            'description' => 'Store performance, traffic and ad spend with AI summaries — for online stores.',
            'sections' => [
                ['cover', 'Cover'],
                ['contents', 'Contents'],
                ['text', 'Introduction'],
                ['ecommerce.summary', 'Store performance', true],
                ['analytics.site_traffic', 'Site traffic', true],
                ['ads.summary', 'Ad performance', true],
                ['ai.summary', 'Month in review'],
                ['closing', 'Thank you'],
            ],
        ],
        'Full Digital Report' => [
            'description' => 'Everything in one report: overview, traffic, search, uptime & performance and leads, each with an AI summary.',
            'sections' => [
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
            ],
        ],
    ];

    public function run(): void
    {
        $registry = app(BlockTypeRegistry::class);

        foreach (self::TEMPLATES as $name => $template) {
            ReportTemplate::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => $template['description'],
                    'blocks' => $this->blocks($registry, $template['sections']),
                ],
            );
        }
    }

    /**
     * Build stored block definitions, seeding each block's default config and
     * turning on the AI summary where asked and supported. Unknown block types
     * (e.g. an integration not installed) are skipped.
     *
     * @param  array<int, array{0: string, 1: string, 2?: bool}>  $sections
     * @return array<int, array{type: string, heading: string, config: ?array<string, mixed>}>
     */
    private function blocks(BlockTypeRegistry $registry, array $sections): array
    {
        $blocks = [];

        foreach ($sections as $section) {
            $type = $section[0];
            $blockType = $registry->find($type);
            if ($blockType === null) {
                continue;
            }

            $config = $blockType->defaultConfig();
            if (($section[2] ?? false) && $blockType->supportsAiSummary()) {
                $config['ai_summary'] = true;
            }

            $blocks[] = ['type' => $type, 'heading' => $section[1], 'config' => $config ?: null];
        }

        return $blocks;
    }
}
