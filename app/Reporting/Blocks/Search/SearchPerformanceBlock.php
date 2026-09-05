<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Search;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Reporting\Support\Insight;
use App\Support\ReportLang;

/**
 * Search performance from a connected search integration (Google Search
 * Console): clicks, impressions, CTR, average position and the top queries.
 */
class SearchPerformanceBlock extends BlockType
{
    /**
     * key => [metric_key, label, fmt, goodUp]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function metrics(): array
    {
        return [
            'clicks' => ['search.clicks', ReportLang::get('search.metric.clicks'), 'number', true],
            'impressions' => ['search.impressions', ReportLang::get('search.metric.impressions'), 'number', true],
            'ctr' => ['search.ctr', ReportLang::get('search.metric.ctr'), 'percent1', true],
            'position' => ['search.position', ReportLang::get('search.metric.avg_position'), 'decimal1', false],
        ];
    }

    public function type(): string
    {
        return 'search.summary';
    }

    public function label(): string
    {
        return ReportLang::get('search.heading');
    }

    public function description(): string
    {
        return 'Clicks, impressions, CTR, average position, a daily search-clicks trend, and top queries and landing pages.';
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
            BlockOption::toggle('show_chart', 'Show search-clicks trend', true),
            BlockOption::toggle('show_queries', 'Show top queries', true),
            BlockOption::number('queries_limit', 'Top queries to show', 8, 3, 20),
            BlockOption::toggle('show_pages', 'Show top landing pages', true),
            BlockOption::number('pages_limit', 'Top pages to show', 8, 3, 20),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise the site\'s Google search performance this month in two to three '
            .'sentences for a non-technical client. Cover clicks, impressions and average '
            .'position versus the prior period, and the leading query. Use only the figures provided.';
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
            'top_query' => $resolved['queries'][0]['label'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::metrics()));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Search, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Search, $context->comparison)
            : [];
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Search, 'search', $context->range) ?? [];

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

        $queries = [];
        if ((bool) $context->block->configValue('show_queries', true)) {
            $limit = (int) $context->block->configValue('queries_limit', 8);
            $queries = array_slice($snapshot['top_queries'] ?? [], 0, $limit);
        }

        $pages = [];
        if ((bool) $context->block->configValue('show_pages', true)) {
            $limit = (int) $context->block->configValue('pages_limit', 8);
            $pages = array_slice($snapshot['top_pages'] ?? [], 0, $limit);
        }

        return [
            'has_data' => $current !== [],
            'metrics' => $metrics,
            'queries' => $queries,
            'pages' => $pages,
            'timeseries' => (bool) $context->block->configValue('show_chart', true) ? ($snapshot['timeseries'] ?? []) : [],
            'insight' => Insight::headline(
                ReportLang::get('search.insight_noun'),
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
