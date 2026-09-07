<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Ai\AiSummariser;
use App\Integrations\CollectorRunner;
use App\Models\Report;
use App\Models\ReportRender;
use App\Models\SiteIntegration;
use Illuminate\Support\Collection;

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
        private readonly AiSummariser $ai,
    ) {}

    /**
     * @param  (callable(int, int): void)|null  $onProgress  Called with (completed, total) as work advances.
     */
    public function generate(Report $report, ?callable $onProgress = null): ReportRender
    {
        $report->load(['site.integrations', 'blocks']);

        $connections = $this->connectionsToCollect($report);
        $total = count($connections) + 1; // +1 for the resolve-and-freeze step
        $done = 0;
        $tick = function () use (&$done, $total, $onProgress): void {
            if ($onProgress !== null) {
                $onProgress($done, $total);
            }
        };

        $tick();
        $range = $report->dateRange();
        $comparison = $report->comparisonRange();

        foreach ($connections as $connection) {
            $this->runner->collectAll($connection, $range);

            if ($comparison !== null) {
                $this->runner->collectAll($connection, $comparison);
            }

            $done++;
            $tick();
        }

        $branding = $this->resolver->branding($report);

        // Resolve every block, then let the summariser fill any empty AI
        // summaries (a no-op when AI is disabled). The result is frozen, so the
        // provider is never called again when the report is viewed or exported.
        $data = $this->ai->augment($report, $this->resolver->resolveAll($report));

        $render = ReportRender::create([
            'report_id' => $report->id,
            'rendered_at' => now(),
            'data' => $data,
            'branding_snapshot' => $branding->toArray(),
            'meta' => [
                'range' => $report->dateRange()->toArray(),
                'comparison' => $report->comparisonRange()?->toArray(),
            ],
        ]);

        $report->update(['generated_at' => now(), 'status' => 'final']);

        $done++;
        $tick();

        return $render;
    }

    /**
     * The site connections whose data the report's visible blocks require, so
     * the exact period is collected before resolving.
     *
     * @return Collection<int, SiteIntegration>
     */
    private function connectionsToCollect(Report $report): Collection
    {
        $neededKeys = $this->neededIntegrationKeys($report);

        if ($neededKeys === []) {
            return collect();
        }

        return $report->site->integrations
            ->filter(fn ($connection): bool => in_array($connection->integration_key, $neededKeys, true))
            ->values();
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
