<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;

/**
 * Provider-agnostic uptime summary. Reads from whichever monitoring integration
 * the site has connected (UptimeRobot, Better Uptime, …) via the shared uptime.*
 * metric layer.
 */
class UptimeSummaryBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'uptime' => ['uptime.percentage', 'Uptime', 'uptime', true],
        'incidents' => ['uptime.incidents', 'Incidents', 'number', false],
        'downtime' => ['uptime.downtime_seconds', 'Downtime', 'duration', false],
        'response_time' => ['uptime.response_time_ms', 'Avg response', 'ms', false],
    ];

    public function type(): string
    {
        return 'uptime.summary';
    }

    public function label(): string
    {
        return 'Uptime summary';
    }

    public function description(): string
    {
        return 'Uptime percentage, incidents, downtime and response time, versus the previous period.';
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
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'uptime' => 'Uptime',
                'incidents' => 'Incidents',
                'downtime' => 'Downtime',
                'response_time' => 'Avg response',
            ], ['uptime', 'incidents', 'downtime', 'response_time']),
            BlockOption::toggle('show_chart', 'Show daily uptime chart', true, 'Only available for providers that report per-day history (Uptime Kuma).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::METRICS));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->comparison)
            : [];

        $showChart = (bool) $context->block->configValue('show_chart', true);
        $snapshot = $showChart
            ? ($context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [])
            : [];

        $metrics = [];
        foreach ($selected as $key) {
            if (! isset(self::METRICS[$key])) {
                continue;
            }
            [$metricKey, $label, $fmt, $goodUp] = self::METRICS[$key];
            $metrics[] = [
                'label' => $label,
                'fmt' => $fmt,
                'goodUp' => $goodUp,
                'current' => $current[$metricKey]['value'] ?? null,
                'previous' => $previous[$metricKey]['value'] ?? null,
            ];
        }

        return [
            'has_data' => $current !== [],
            'metrics' => $metrics,
            'timeseries' => $snapshot['timeseries'] ?? [],
            'insight' => $this->insight($current),
        ];
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     */
    private function insight(array $current): ?string
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

        return $sentence;
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
