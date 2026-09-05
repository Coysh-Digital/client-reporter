<?php

declare(strict_types=1);

namespace App\Integrations\DownloadTracker;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Pulls Download Tracker's aggregate download stats: a period total and file
 * count as metrics, plus a snapshot with the top files and a daily timeseries
 * for the report block's chart.
 */
class DownloadsCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'downloads';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = (new DownloadTrackerIntegration)->client($connection);

        $data = $client->get('report', [
            'from' => $range->start->toDateString(),
            'to' => $range->end->toDateString(),
        ]);

        /** @var array<string, mixed> $metrics */
        $metrics = $data['metrics'] ?? [];

        return CollectorResult::make()
            ->metric('downloads.total', (float) ($metrics['downloads'] ?? 0))
            ->metric('downloads.files', (float) ($metrics['files'] ?? 0))
            ->snapshot([
                'provider' => 'Download Tracker',
                'top_files' => $this->rows($data['top_files'] ?? []),
                'timeseries' => $this->timeseries($data['timeseries'] ?? []),
            ]);
    }

    /**
     * @return array<int, array{label: string, downloads: int}>
     */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = ['label' => (string) ($row['label'] ?? ''), 'downloads' => (int) ($row['downloads'] ?? 0)];
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, value: int}>
     */
    private function timeseries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        $out = [];
        foreach ($series as $point) {
            if (! is_array($point)) {
                continue;
            }
            $out[] = ['date' => (string) ($point['date'] ?? ''), 'value' => (int) ($point['value'] ?? 0)];
        }

        return $out;
    }
}
