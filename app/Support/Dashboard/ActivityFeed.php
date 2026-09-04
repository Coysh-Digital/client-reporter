<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Models\CollectorRun;
use App\Models\ReportRender;
use App\Models\ReportShare;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A read-only recent-activity feed, derived by unioning the timestamped records
 * the app already keeps — collector runs, report renders and report shares — so
 * no dedicated activity table (and no extra writes on the hot collection path)
 * is needed. Successful collector runs are capped so routine hourly syncs don't
 * drown out the signal.
 *
 * @phpstan-type ActivityItem array{variant: string, label: string, entity: string|null, entityUrl: string|null, when: CarbonImmutable}
 */
class ActivityFeed
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 12): array
    {
        $items = [];

        foreach ($this->collectorEvents() as $item) {
            $items[] = $item;
        }
        foreach ($this->reportEvents() as $item) {
            $items[] = $item;
        }

        usort($items, fn (array $a, array $b): int => $b['when'] <=> $a['when']);

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectorEvents(): array
    {
        $runs = CollectorRun::query()
            ->where('status', 'failed')
            ->whereNotNull('finished_at')
            ->with('siteIntegration.site.client')
            ->latest('finished_at')
            ->limit(8)
            ->get()
            ->concat(
                CollectorRun::query()
                    ->where('status', 'success')
                    ->whereNotNull('finished_at')
                    ->with('siteIntegration.site.client')
                    ->latest('finished_at')
                    ->limit(6)
                    ->get()
            );

        $items = [];
        foreach ($runs as $run) {
            $when = $this->carbon($run->finished_at);
            if ($when === null) {
                continue;
            }
            $ok = $run->status === 'success';
            $site = $run->siteIntegration?->site;
            $items[] = [
                'variant' => $ok ? 'ok' : 'danger',
                'label' => $ok ? 'Data collected for' : 'Sync failed for',
                'entity' => $site?->name,
                'entityUrl' => $site !== null ? route('sites.show', $site) : null,
                'when' => $when,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportEvents(): array
    {
        $items = [];

        $renders = ReportRender::query()
            ->whereNotNull('rendered_at')
            ->with('report.site')
            ->latest('rendered_at')
            ->limit(8)
            ->get();

        foreach ($renders as $render) {
            $when = $this->carbon($render->rendered_at);
            $report = $render->report;
            if ($when === null || $report === null) {
                continue;
            }
            $items[] = [
                'variant' => 'accent',
                'label' => 'Report generated for',
                'entity' => $report->site->name,
                'entityUrl' => route('reports.show', $report),
                'when' => $when,
            ];
        }

        $shares = ReportShare::query()
            ->with('report.site')
            ->latest('created_at')
            ->limit(8)
            ->get();

        foreach ($shares as $share) {
            $when = $this->carbon($share->created_at);
            $report = $share->report;
            if ($when === null || $report === null) {
                continue;
            }
            $items[] = [
                'variant' => 'info',
                'label' => 'Report shared for',
                'entity' => $report->site->name,
                'entityUrl' => route('reports.show', $report),
                'when' => $when,
            ];
        }

        return $items;
    }

    private function carbon(CarbonInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
