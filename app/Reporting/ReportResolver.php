<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportBlock;
use App\Reporting\Support\BlockContext;
use App\Support\Branding\BrandingResolver;
use App\Support\Branding\ResolvedBranding;
use App\Support\MergeTags;

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

        $resolved = $type->resolve($context);

        // Carry the persisted AI summary draft (previewed or hand-edited in the
        // builder) through into the block data, so it shows in the live preview
        // and is captured when the render is frozen. The generator fills any
        // that are still empty at generate time.
        if ($block->ai_summary !== null && $block->ai_summary !== '') {
            $resolved['ai_summary'] = $block->ai_summary;
        }

        return $resolved;
    }

    /**
     * Resolve every visible block into a keyed payload for rendering/freezing.
     * Empty sections set to hide are dropped, and the table of contents is
     * pruned to match, so a frozen render never carries — or links to — a
     * section that has nothing to show.
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

            $resolved = $this->resolveBlock($report, $block, $branding);

            if ($this->hiddenWhenEmpty($block, $resolved)) {
                continue;
            }

            $data[$block->id] = [
                'type' => $block->type,
                'heading' => MergeTags::apply($block->heading, $report, $branding),
                'commentary' => MergeTags::apply($block->commentary, $report, $branding),
                'data' => $resolved,
            ];
        }

        return self::pruneContents($data);
    }

    /**
     * Whether a block should be left out because it has no data and is set to
     * hide when empty (the default for every empty-capable section).
     *
     * @param  array<string, mixed>  $resolved  the block's resolve() output
     */
    public function hiddenWhenEmpty(ReportBlock $block, array $resolved): bool
    {
        $type = $this->blocks->find($block->type);

        return $type !== null
            && $type->canBeEmpty()
            && (bool) $block->configValue('hide_when_empty', true)
            && $type->isEmpty($resolved);
    }

    /**
     * Drop table-of-contents entries that point at sections not present in the
     * given set (hidden, or empty and set to hide), so the contents never links
     * to a section that isn't in the report.
     *
     * @param  array<int, array<string, mixed>>  $data  entries keyed by block id
     * @return array<int, array<string, mixed>>
     */
    public static function pruneContents(array $data): array
    {
        $present = [];
        foreach (array_keys($data) as $id) {
            $present['block-'.$id] = true;
        }

        foreach ($data as $id => $entry) {
            if (($entry['type'] ?? null) !== 'contents') {
                continue;
            }

            $items = $entry['data']['items'] ?? [];
            $data[$id]['data']['items'] = array_values(array_filter(
                $items,
                static fn (array $item): bool => isset($present[$item['anchor'] ?? '']),
            ));
        }

        return $data;
    }
}
