<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportBlock;
use App\Reporting\Support\BlockContext;
use App\Support\Branding\BrandingResolver;
use App\Support\Branding\ResolvedBranding;

/**
 * Resolves live block data for a report (used for the builder preview and by the
 * generator when freezing a render).
 */
class ReportResolver
{
    public function __construct(
        private readonly BlockTypeRegistry $blocks,
        private readonly MetricReader $reader,
        private readonly BrandingResolver $branding,
    ) {}

    public function branding(Report $report): ResolvedBranding
    {
        return $this->branding->forSite($report->site);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveBlock(Report $report, ReportBlock $block, ?ResolvedBranding $branding = null): array
    {
        $type = $this->blocks->find($block->type);

        if ($type === null) {
            return ['__unavailable' => true];
        }

        $context = new BlockContext(
            site: $report->site,
            block: $block,
            range: $report->dateRange(),
            comparison: $report->comparisonRange(),
            reader: $this->reader,
            branding: $branding ?? $this->branding($report),
        );

        return $type->resolve($context);
    }

    /**
     * Resolve every visible block into a keyed payload for rendering/freezing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveAll(Report $report): array
    {
        $branding = $this->branding($report);
        $data = [];

        foreach ($report->blocks as $block) {
            if ($block->is_hidden) {
                continue;
            }

            $data[$block->id] = [
                'type' => $block->type,
                'heading' => $block->heading,
                'commentary' => $block->commentary,
                'data' => $this->resolveBlock($report, $block, $branding),
            ];
        }

        return $data;
    }
}
