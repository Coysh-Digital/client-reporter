<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Reporting\Support\Insight;

class AnalyticsSummaryBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'visitors' => ['analytics.visitors', 'Visitors', 'number', true],
        'pageviews' => ['analytics.pageviews', 'Page views', 'number', true],
        'visits' => ['analytics.visits', 'Visits', 'number', true],
        'bounce_rate' => ['analytics.bounce_rate', 'Bounce rate', 'percent', false],
        'visit_duration' => ['analytics.visit_duration', 'Avg visit duration', 'duration', true],
    ];

    public function type(): string
    {
        return 'analytics.summary';
    }

    public function label(): string
    {
        return 'Analytics summary';
    }

    public function description(): string
    {
        return 'Visitors, page views and engagement versus the previous period.';
    }

    public function group(): string
    {
        return 'Analytics';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Analytics;
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'visitors' => 'Visitors',
                'pageviews' => 'Page views',
                'visits' => 'Visits',
                'bounce_rate' => 'Bounce rate',
                'visit_duration' => 'Avg visit duration',
            ], ['visitors', 'pageviews', 'visits', 'bounce_rate']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::METRICS));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->comparison)
            : [];

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];

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
            'provider' => $snapshot['provider'] ?? null,
            'metrics' => $metrics,
            'insight' => Insight::headline(
                'visitors',
                $current['analytics.visitors']['value'] ?? null,
                $previous['analytics.visitors']['value'] ?? null,
            ),
        ];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
