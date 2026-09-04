<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Support\DateRange;

/**
 * Creates a draft Report for a site and seeds its blocks — either from a chosen
 * template or a sensible default set — skipping any block the site can't feed.
 * Shared by the manual "New report" screen and the scheduled-report command so
 * both build reports the same way.
 */
class ReportComposer
{
    /**
     * The default sections used when no template is chosen.
     *
     * @var array<int, array{type: string, heading: string}>
     */
    public const DEFAULT_BLOCKS = [
        ['type' => 'cover', 'heading' => 'Cover'],
        ['type' => 'text', 'heading' => 'Introduction'],
        ['type' => 'website-overview', 'heading' => 'Website overview'],
        ['type' => 'analytics.site_traffic', 'heading' => 'Site traffic'],
        ['type' => 'uptime.overview', 'heading' => 'Uptime & performance'],
        ['type' => 'closing', 'heading' => 'Thank you'],
    ];

    public function compose(
        Site $site,
        DateRange $range,
        string $title,
        ?ReportTemplate $template = null,
        bool $comparePrevious = true,
        ?int $createdBy = null,
        bool $scheduled = false,
    ): Report {
        $report = Report::query()->create([
            'site_id' => $site->id,
            'report_template_id' => $template?->id,
            'title' => $title,
            'range_start' => $range->start->toDateString(),
            'range_end' => $range->end->toDateString(),
            'compare_previous' => $comparePrevious,
            'created_by' => $createdBy,
            'status' => 'draft',
            'scheduled' => $scheduled,
        ]);

        $this->seedBlocks($report, $template);

        return $report;
    }

    private function seedBlocks(Report $report, ?ReportTemplate $template): void
    {
        $definitions = self::DEFAULT_BLOCKS;

        if ($template !== null && $template->blocks !== []) {
            $definitions = $template->blocks;
        }

        $registry = app(BlockTypeRegistry::class);
        $availability = app(BlockAvailability::class);
        $connectedKeys = $availability->connectedKeys($report->site);

        $position = 0;
        foreach (array_values($definitions) as $definition) {
            $blockType = $registry->find($definition['type']);

            // Only seed blocks the registry knows AND the site can actually feed.
            if ($blockType === null || ! $availability->isAvailable($blockType, $report->site, $connectedKeys)) {
                continue;
            }

            $report->blocks()->create([
                'type' => $definition['type'],
                'position' => $position++,
                'heading' => $definition['heading'] ?? $blockType->label(),
                'config' => $definition['config'] ?? ($blockType->defaultConfig() ?: null),
            ]);
        }
    }
}
