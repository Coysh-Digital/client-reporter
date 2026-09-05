<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;
use App\Support\ReportLang;

/**
 * Provider-agnostic uptime summary. Reads from whichever monitoring integration
 * the site has connected (UptimeRobot, Better Uptime, …) via the shared uptime.*
 * metric layer.
 */
class UptimeSummaryBlock extends BlockType
{
    /**
     * key => [metric_key, label, fmt, goodUp]. A method rather than a const so
     * the labels can be resolved through the report language dictionary.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function metrics(): array
    {
        return [
            'uptime' => ['uptime.percentage', ReportLang::get('uptime.metric.uptime'), 'uptime', true],
            'incidents' => ['uptime.incidents', ReportLang::get('uptime.metric.incidents'), 'number', false],
            'downtime' => ['uptime.downtime_seconds', ReportLang::get('uptime.metric.downtime'), 'duration', false],
            'response_time' => ['uptime.response_time_ms', ReportLang::get('uptime.metric.avg_response'), 'ms', false],
        ];
    }

    public function type(): string
    {
        return 'uptime.summary';
    }

    public function label(): string
    {
        return ReportLang::get('uptime_summary.label');
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
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise the website\'s uptime this month in two to three sentences for a '
            .'non-technical client. Mention availability, any incidents and the average response '
            .'time. Use only the figures provided.';
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    public function aiFacts(array $resolved): array
    {
        if (! ($resolved['has_data'] ?? false)) {
            return [];
        }

        $metrics = [];
        foreach ($resolved['metrics'] ?? [] as $metric) {
            $metrics[$metric['label']] = ['current' => $metric['current'], 'previous' => $metric['previous']];
        }

        return array_filter([
            'metrics' => $metrics,
        ], fn ($value): bool => $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::metrics()));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->comparison)
            : [];

        $showChart = (bool) $context->block->configValue('show_chart', true);
        $snapshot = $showChart
            ? ($context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [])
            : [];

        $metrics = [];
        $definitions = self::metrics();
        foreach ($selected as $key) {
            if (! isset($definitions[$key])) {
                continue;
            }
            [$metricKey, $label, $fmt, $goodUp] = $definitions[$key];
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

        $sentence = ReportLang::get('uptime.summary.base', ['uptime' => Format::percent($uptime, 2)]);

        $response = $current['uptime.response_time_ms']['value'] ?? null;
        if ($response !== null) {
            $sentence .= ReportLang::get('uptime.summary.response', ['response' => Format::forType($response, 'ms')]);
        } else {
            $sentence .= ReportLang::get('uptime.summary.no_response');
        }

        $incidents = (int) ($current['uptime.incidents']['value'] ?? 0);
        if ($incidents > 0) {
            $sentence .= ReportLang::get(
                $incidents === 1 ? 'uptime.summary.incident_singular' : 'uptime.summary.incident_plural',
                ['count' => $incidents],
            );
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
