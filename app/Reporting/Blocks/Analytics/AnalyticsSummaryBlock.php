<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Reporting\Support\Insight;
use App\Support\ReportLang;

class AnalyticsSummaryBlock extends BlockType
{
    /**
     * key => [metric_key, label, fmt, goodUp]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function metrics(): array
    {
        return [
            'visitors' => ['analytics.visitors', ReportLang::get('analytics.metric.visitors'), 'number', true],
            'pageviews' => ['analytics.pageviews', ReportLang::get('analytics.metric.pageviews'), 'number', true],
            'visits' => ['analytics.visits', ReportLang::get('analytics.metric.visits'), 'number', true],
            'bounce_rate' => ['analytics.bounce_rate', ReportLang::get('analytics.metric.bounce_rate'), 'percent', false],
            'visit_duration' => ['analytics.visit_duration', ReportLang::get('analytics.metric.visit_duration'), 'duration', true],
        ];
    }

    public function type(): string
    {
        return 'analytics.summary';
    }

    public function label(): string
    {
        return ReportLang::get('analytics.label');
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
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this month\'s website analytics for a non-technical client in two to '
            .'three sentences. Cover visitors and engagement and how they moved versus the prior '
            .'period. Use only the figures provided.';
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
            'provider' => $resolved['provider'] ?? null,
            'metrics' => $metrics,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::metrics()));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->comparison)
            : [];

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];

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
            'provider' => $snapshot['provider'] ?? null,
            'metrics' => $metrics,
            'insight' => Insight::headline(
                ReportLang::get('analytics.insight_noun'),
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
