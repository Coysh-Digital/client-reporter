<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportRender;
use App\Support\Branding\ResolvedBranding;

/**
 * Assembles the data the report document view needs — the resolved branding and
 * an ordered list of renderable blocks — either live (draft preview) or from a
 * frozen render (shared links, PDF, email).
 */
class ReportDocument
{
    public function __construct(
        private readonly ReportResolver $resolver,
        private readonly BlockTypeRegistry $blocks,
    ) {}

    /**
     * @return array{report: Report, branding: ResolvedBranding, blocks: array<int, array<string, mixed>>, frozen: bool}
     */
    public function live(Report $report): array
    {
        $branding = $this->resolver->branding($report);
        $blocks = [];

        foreach ($report->blocks as $block) {
            if ($block->is_hidden) {
                continue;
            }

            $data = $this->resolver->resolveBlock($report, $block, $branding);

            if ($this->resolver->hiddenWhenEmpty($block, $data)) {
                continue;
            }

            $blocks[] = [
                'id' => $block->id,
                'view' => $this->viewFor($block->type),
                'type' => $block->type,
                'heading' => $block->heading,
                'commentary' => $block->commentary,
                'icon' => $this->iconFor($block->type),
                'data' => $data,
            ];
        }

        return ['report' => $report, 'branding' => $branding, 'blocks' => $this->pruneContents($blocks), 'frozen' => false];
    }

    /**
     * @return array{report: Report, branding: ResolvedBranding, blocks: array<int, array<string, mixed>>, frozen: bool}
     */
    public function fromRender(ReportRender $render): array
    {
        $render->loadMissing('report.site.client');
        $branding = ResolvedBranding::fromArray($render->branding_snapshot);
        $blocks = [];

        foreach ($render->data as $blockId => $entry) {
            $blocks[] = [
                'id' => (int) $blockId,
                'view' => $this->viewFor($entry['type'] ?? ''),
                'type' => $entry['type'] ?? '',
                'heading' => $entry['heading'] ?? null,
                'commentary' => $entry['commentary'] ?? null,
                'icon' => $this->iconFor($entry['type'] ?? ''),
                'data' => $entry['data'] ?? [],
            ];
        }

        return ['report' => $render->report, 'branding' => $branding, 'blocks' => $blocks, 'frozen' => true];
    }

    /**
     * Drop table-of-contents entries pointing at sections that aren't in the
     * rendered list (hidden, or empty and set to hide), so the live preview's
     * contents matches the frozen render's.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function pruneContents(array $blocks): array
    {
        $present = [];
        foreach ($blocks as $block) {
            $present['block-'.$block['id']] = true;
        }

        foreach ($blocks as $i => $block) {
            if (($block['type'] ?? null) !== 'contents') {
                continue;
            }

            $items = $block['data']['items'] ?? [];
            $blocks[$i]['data']['items'] = array_values(array_filter(
                $items,
                static fn (array $item): bool => isset($present[$item['anchor'] ?? '']),
            ));
        }

        return $blocks;
    }

    private function viewFor(string $type): string
    {
        return $this->blocks->find($type)?->view() ?? 'reports.blocks.unavailable';
    }

    private function iconFor(string $type): string
    {
        return $this->blocks->find($type)?->icon() ?? 'document';
    }
}
