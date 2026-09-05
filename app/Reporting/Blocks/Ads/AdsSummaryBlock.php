<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Ads;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;
use App\Support\ReportLang;

/**
 * Ad platform performance. Unlike Ecommerce/Uptime/Forms, this is tied to a
 * specific integration rather than a whole category: a site can have both a
 * traffic-analytics provider (GA4, Plausible, …) and an ad platform connected
 * at once, so picking "the" Analytics-category connection would be ambiguous.
 */
class AdsSummaryBlock extends BlockType
{
    /**
     * key => [metric_key, label, fmt, goodUp]. A method rather than a const so
     * the labels resolve through the report language dictionary.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function metrics(): array
    {
        return [
            'spend' => ['ads.spend', ReportLang::get('ads.metric.spend'), 'money', false],
            'clicks' => ['ads.clicks', ReportLang::get('ads.metric.clicks'), 'number', true],
            'impressions' => ['ads.impressions', ReportLang::get('ads.metric.impressions'), 'number', true],
            'conversions' => ['ads.conversions', ReportLang::get('ads.metric.conversions'), 'number', true],
        ];
    }

    public function type(): string
    {
        return 'ads.summary';
    }

    public function label(): string
    {
        return ReportLang::get('ads.heading');
    }

    public function description(): string
    {
        return 'Spend, clicks, impressions and conversions from a connected ad platform, versus the previous period.';
    }

    public function group(): string
    {
        return 'Analytics';
    }

    public function requiresIntegration(): ?string
    {
        return 'google_ads';
    }

    /**
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'spend' => 'Spend',
                'clicks' => 'Clicks',
                'impressions' => 'Impressions',
                'conversions' => 'Conversions',
            ], ['spend', 'clicks', 'impressions', 'conversions']),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise the ad platform\'s performance this month in two to three sentences '
            .'for a non-technical client. Cover spend, clicks and conversions versus the prior '
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
            'currency' => $resolved['currency'] ?? null,
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

        $current = $context->reader->metrics($context->site, 'google_ads', $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metrics($context->site, 'google_ads', $context->comparison)
            : [];
        $snapshot = $context->reader->snapshot($context->site, 'google_ads', 'summary', $context->range) ?? [];
        $currency = $snapshot['currency'] ?? ($current['ads.spend']['unit'] ?? null);

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
            'currency' => $currency,
            'metrics' => $metrics,
            'insight' => $this->insight($current, $currency),
        ];
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     */
    private function insight(array $current, ?string $currency): ?string
    {
        $spend = $current['ads.spend']['value'] ?? null;
        $clicks = $current['ads.clicks']['value'] ?? null;

        if ($spend === null || $clicks === null) {
            return null;
        }

        return ReportLang::get('ads.insight.text', [
            'spend' => Format::money($spend, $currency),
            'count' => Format::number($clicks),
            'noun' => ReportLang::get((int) $clicks === 1 ? 'ads.insight.click' : 'ads.insight.clicks'),
        ]);
    }

    public function icon(): string
    {
        return 'chart';
    }
}
