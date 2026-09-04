<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Integrations\CollectorRunner;
use App\Models\Report;
use App\Models\ReportRender;

/**
 * Generates a report: ensures the exact report period (and its comparison
 * period) is collected for every integration the report's blocks need, then
 * resolves all blocks and freezes the result into a ReportRender so the report
 * loads fast and stays stable when shared, emailed or exported.
 */
class ReportGenerator
{
    public function __construct(
        private readonly BlockTypeRegistry $blocks,
        private readonly ReportResolver $resolver,
        private readonly CollectorRunner $runner,
    ) {}

    public function generate(Report $report): ReportRender
    {
        $report->load(['site.integrations', 'blocks']);

        $this->ensureCollected($report);

        $branding = $this->resolver->branding($report);

        $render = ReportRender::create([
            'report_id' => $report->id,
            'rendered_at' => now(),
            'data' => $this->resolver->resolveAll($report),
            'branding_snapshot' => $branding->toArray(),
            'meta' => [
                'range' => $report->dateRange()->toArray(),
                'comparison' => $report->comparisonRange()?->toArray(),
            ],
        ]);

        $report->update(['generated_at' => now(), 'status' => 'final']);

        return $render;
    }

    /**
     * Collect the report's period (and comparison) for the integrations its
     * visible blocks require, so exact-period data exists before resolving.
     */
    private function ensureCollected(Report $report): void
    {
        $neededKeys = $this->neededIntegrationKeys($report);

        if ($neededKeys === []) {
            return;
        }

        $range = $report->dateRange();
        $comparison = $report->comparisonRange();

        foreach ($report->site->integrations as $connection) {
            if (! in_array($connection->integration_key, $neededKeys, true)) {
                continue;
            }

            $this->runner->collectAll($connection, $range);

            if ($comparison !== null) {
                $this->runner->collectAll($connection, $comparison);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function neededIntegrationKeys(Report $report): array
    {
        $keys = [];

        foreach ($report->blocks->reject(fn ($block) => $block->is_hidden) as $block) {
            $type = $this->blocks->find($block->type);
            if ($type === null) {
                continue;
            }

            $keys = array_merge($keys, $type->neededIntegrationKeys($report->site));
        }

        return array_values(array_unique($keys));
    }
}
