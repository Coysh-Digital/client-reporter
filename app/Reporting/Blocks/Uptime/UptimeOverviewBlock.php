<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;

/**
 * A consolidated uptime & performance component: headline availability metrics,
 * a daily uptime strip and trend, the Lighthouse performance score, and the
 * period's incidents — in one panel. Reads whatever the site's monitoring (and,
 * if connected, performance) integrations collected; panels with no data are
 * omitted rather than shown empty.
 */
class UptimeOverviewBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'uptime' => ['uptime.percentage', 'Avg uptime', 'uptime', true],
        'response_time' => ['uptime.response_time_ms', 'Avg response', 'ms', false],
        'incidents' => ['uptime.incidents', 'Incidents', 'number', false],
        'downtime' => ['uptime.downtime_seconds', 'Downtime', 'duration', false],
        'monitors' => ['uptime.monitors', 'Monitors', 'number', true],
    ];

    public function type(): string
    {
        return 'uptime.overview';
    }

    public function label(): string
    {
        return 'Uptime & performance';
    }

    public function description(): string
    {
        return 'Availability, response time, a daily uptime strip, the Lighthouse performance score and incidents in one panel.';
    }

    public function group(): string
    {
        return 'Uptime';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Monitoring;
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::number('incident_limit', 'Incidents to show', 10, 1, 50),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->comparison)
            : [];

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [];

        $tiles = [];
        foreach (self::METRICS as [$metricKey, $label, $fmt, $goodUp]) {
            $tiles[] = [
                'label' => $label,
                'fmt' => $fmt,
                'goodUp' => $goodUp,
                'current' => $current[$metricKey]['value'] ?? null,
                'previous' => $previous[$metricKey]['value'] ?? null,
            ];
        }

        $limit = (int) $context->block->configValue('incident_limit', 10);

        // Lighthouse performance score, only if a performance integration is connected.
        $performance = $context->reader->metricsForCategory($context->site, IntegrationCategory::Performance, $context->range);
        $score = $performance['performance.score']['value'] ?? null;

        return [
            'has_data' => $current !== [],
            'summary' => $this->summary($current, $score),
            'tiles' => $tiles,
            'timeseries' => $snapshot['timeseries'] ?? [],
            'status_days' => $this->statusDays($snapshot['timeseries'] ?? []),
            'incidents' => array_slice($snapshot['incidents'] ?? [], 0, $limit),
            'performance_score' => $score === null ? null : (int) round($score),
            'performance_rating' => $score === null ? null : $this->rating((int) round($score)),
        ];
    }

    /**
     * Classify each day's uptime percentage for the status strip.
     *
     * @param  array<int, array<string, mixed>>  $timeseries
     * @return array<int, array{date: string, status: string}>
     */
    private function statusDays(array $timeseries): array
    {
        return array_map(function (array $day): array {
            $value = (float) ($day['value'] ?? 0);

            $status = match (true) {
                $value <= 0 => 'none',      // no checks recorded that day
                $value >= 99.9 => 'healthy',
                $value >= 99.5 => 'partial',
                default => 'below',
            };

            return ['date' => (string) ($day['date'] ?? ''), 'status' => $status];
        }, $timeseries);
    }

    private function rating(int $score): string
    {
        return match (true) {
            $score >= 90 => 'good',
            $score >= 50 => 'needs-improvement',
            default => 'poor',
        };
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     */
    private function summary(array $current, ?float $score): ?string
    {
        $uptime = $current['uptime.percentage']['value'] ?? null;
        if ($uptime === null) {
            return null;
        }

        $sentence = 'The site held '.Format::percent($uptime, 2).' uptime';

        $response = $current['uptime.response_time_ms']['value'] ?? null;
        if ($response !== null) {
            $sentence .= ' with an average response time of '.Format::forType($response, 'ms').'.';
        } else {
            $sentence .= ' this period.';
        }

        $incidents = (int) ($current['uptime.incidents']['value'] ?? 0);
        if ($incidents > 0) {
            $sentence .= ' '.$incidents.' '.($incidents === 1 ? 'incident was' : 'incidents were').' detected during the period.';
        }

        if ($score !== null) {
            $sentence .= ' Lighthouse performance sits at '.(int) round($score).'.';
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
