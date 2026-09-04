<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Performance;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

/**
 * Core Web Vitals + performance score for the site, from any performance
 * integration (PageSpeed / CrUX). Each vital is rated good / needs work / poor
 * against Google's thresholds.
 */
class PerformanceSummaryBlock extends BlockType
{
    public function type(): string
    {
        return 'performance.summary';
    }

    public function label(): string
    {
        return 'Core Web Vitals';
    }

    public function description(): string
    {
        return 'Largest Contentful Paint, Interaction to Next Paint and Cumulative Layout Shift, with a performance score.';
    }

    public function group(): string
    {
        return 'Performance';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Performance;
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('show_score', 'Show performance score', true),
            BlockOption::toggle('show_chart', 'Show score history chart', true, 'Builds up day by day from when this site was connected.'),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise the site\'s Core Web Vitals this month in two to three sentences for a '
            .'non-technical client, noting the performance score and any vitals needing attention. '
            .'Use only the figures provided.';
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

        $vitals = [];
        foreach ($resolved['vitals'] ?? [] as $vital) {
            $vitals[$vital['key']] = ['value' => $vital['value'], 'rating' => $vital['rating']];
        }

        return array_filter([
            'score' => $resolved['score'] ?? null,
            'vitals' => $vitals,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $metrics = $context->reader->metricsForCategory($context->site, IntegrationCategory::Performance, $context->range);
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Performance, 'core-web-vitals', $context->range) ?? [];

        $lcp = $metrics['performance.lcp_ms']['value'] ?? null;
        $inp = $metrics['performance.inp_ms']['value'] ?? null;
        $cls = $metrics['performance.cls']['value'] ?? null;
        $score = $metrics['performance.score']['value'] ?? null;

        $vitals = [
            $this->vital('LCP', 'Largest Contentful Paint', $lcp, $lcp === null ? '—' : round($lcp / 1000, 1).'s', $this->rate($lcp, 2500, 4000)),
            $this->vital('INP', 'Interaction to Next Paint', $inp, $inp === null ? '—' : round($inp).'ms', $this->rate($inp, 200, 500)),
            $this->vital('CLS', 'Cumulative Layout Shift', $cls, $cls === null ? '—' : number_format((float) $cls, 2), $this->rate($cls, 0.1, 0.25)),
        ];

        $scoreValue = $score !== null ? (int) round((float) $score) : null;

        return [
            'has_data' => $metrics !== [],
            'source' => $snapshot['source'] ?? 'field',
            'strategy' => $snapshot['strategy'] ?? 'mobile',
            'show_score' => (bool) $context->block->configValue('show_score', true),
            'score' => $scoreValue,
            'score_rating' => $score === null ? null : ($score >= 90 ? 'good' : ($score >= 50 ? 'needs-improvement' : 'poor')),
            'vitals' => $vitals,
            'timeseries' => (bool) $context->block->configValue('show_chart', true) ? ($snapshot['timeseries'] ?? []) : [],
            'insight' => $this->insight($scoreValue, $vitals),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, value: string, rating: ?string}>  $vitals
     */
    private function insight(?int $score, array $vitals): ?string
    {
        if ($score === null) {
            return null;
        }

        $rating = $score >= 90 ? 'a good' : ($score >= 50 ? 'a needs-improvement' : 'a poor');
        $sentence = 'The site scored '.$score.' on performance, '.$rating.' rating.';

        $poor = array_values(array_filter($vitals, fn (array $v): bool => $v['rating'] === 'poor'));
        if ($poor !== []) {
            $labels = implode(' and ', array_map(fn (array $v): string => $v['key'], $poor));
            $sentence .= ' '.$labels.' '.(count($poor) === 1 ? 'needs' : 'need').' attention.';
        }

        return $sentence;
    }

    /**
     * @return array{key: string, label: string, value: string, rating: ?string}
     */
    private function vital(string $key, string $label, int|float|null $raw, string $display, ?string $rating): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $display, 'rating' => $raw === null ? null : $rating];
    }

    private function rate(int|float|null $value, float $good, float $poor): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value <= $good ? 'good' : ($value <= $poor ? 'needs-improvement' : 'poor');
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
