<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Search;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Reporting\Support\Insight;

/**
 * Search performance from a connected search integration (Google Search
 * Console): clicks, impressions, CTR, average position and the top queries.
 */
class SearchPerformanceBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'clicks' => ['search.clicks', 'Clicks', 'number', true],
        'impressions' => ['search.impressions', 'Impressions', 'number', true],
        'ctr' => ['search.ctr', 'CTR', 'percent1', true],
        'position' => ['search.position', 'Avg position', 'decimal1', false],
    ];

    public function type(): string
    {
        return 'search.summary';
    }

    public function label(): string
    {
        return 'Search performance';
    }

    public function description(): string
    {
        return 'Clicks, impressions, click-through rate, average position and top search queries.';
    }

    public function group(): string
    {
        return 'Search';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Search;
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'clicks' => 'Clicks',
                'impressions' => 'Impressions',
                'ctr' => 'CTR',
                'position' => 'Avg position',
            ], ['clicks', 'impressions', 'ctr', 'position']),
            BlockOption::toggle('show_queries', 'Show top queries', true),
            BlockOption::number('queries_limit', 'Top queries to show', 8, 3, 20),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::METRICS));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Search, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Search, $context->comparison)
            : [];
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Search, 'search', $context->range) ?? [];

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

        $queries = [];
        if ((bool) $context->block->configValue('show_queries', true)) {
            $limit = (int) $context->block->configValue('queries_limit', 8);
            $queries = array_slice($snapshot['top_queries'] ?? [], 0, $limit);
        }

        return [
            'has_data' => $current !== [],
            'metrics' => $metrics,
            'queries' => $queries,
            'insight' => Insight::headline(
                'clicks from Google search',
                $current['search.clicks']['value'] ?? null,
                $previous['search.clicks']['value'] ?? null,
            ),
        ];
    }

    public function icon(): string
    {
        return 'search';
    }
}
