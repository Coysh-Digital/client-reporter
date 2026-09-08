<?php

declare(strict_types=1);

namespace App\Integrations\WordPress\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;
use App\Support\ReportLang;

/**
 * Form submissions from a WordPress site's Gravity Forms / Ninja Forms, pulled
 * through the connector: the period total and number of forms as headline tiles,
 * a daily "responses over time" trend with a per-day activity strip, and the
 * busiest forms. Renders nothing useful (an empty note) when the site has no
 * forms plugin — so it is safe to leave in a template for every site.
 */
class FormResponsesBlock extends BlockType
{
    public function type(): string
    {
        return 'forms.responses';
    }

    public function label(): string
    {
        return ReportLang::get('form_responses.heading');
    }

    public function description(): string
    {
        return 'Form submissions over time from Gravity Forms or Ninja Forms: a daily trend, a per-day activity strip and the busiest forms.';
    }

    public function group(): string
    {
        return 'Forms & Leads';
    }

    public function icon(): string
    {
        return 'chart';
    }

    public function requiresIntegration(): ?string
    {
        return 'wordpress';
    }

    /**
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::toggle('show_chart', 'Show responses-over-time chart', true),
            BlockOption::toggle('show_forms', 'Show busiest forms', true),
            BlockOption::number('forms_limit', 'Forms to list', 8, 3, 25),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this period\'s website form submissions for a non-technical client in '
            .'two to three sentences. Cover the total responses and how it moved versus the prior '
            .'period, and the busiest form. Use only the figures provided.';
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
        foreach ($resolved['tiles'] ?? [] as $tile) {
            $metrics[$tile['label']] = ['current' => $tile['current'], 'previous' => $tile['previous']];
        }

        return array_filter([
            'metrics' => $metrics,
            'busiest_form' => $resolved['forms'][0]['name'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $current = $context->reader->metrics($context->site, 'wordpress', $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metrics($context->site, 'wordpress', $context->comparison)
            : [];

        $snapshot = $context->reader->snapshot($context->site, 'wordpress', 'forms', $context->range) ?? [];
        $previousSnapshot = $compare && $context->comparison
            ? ($context->reader->snapshot($context->site, 'wordpress', 'forms', $context->comparison) ?? [])
            : [];

        $tile = fn (string $key, string $label): array => [
            'label' => $label,
            'fmt' => 'number',
            'goodUp' => true,
            'current' => $current[$key]['value'] ?? null,
            'previous' => $previous[$key]['value'] ?? null,
        ];

        $forms = [];
        if ((bool) $context->block->configValue('show_forms', true)) {
            $limit = (int) $context->block->configValue('forms_limit', 8);
            $forms = array_slice($snapshot['forms'] ?? [], 0, $limit);
        }

        $timeseries = (bool) $context->block->configValue('show_chart', true) ? ($snapshot['timeseries'] ?? []) : [];

        return [
            // Only present when the connector reported an active forms plugin.
            'has_data' => ($snapshot['active'] ?? false) === true && isset($current['forms.responses']),
            'tiles' => [
                $tile('forms.responses', ReportLang::get('form_responses.tile.responses')),
                $tile('forms.forms', ReportLang::get('form_responses.tile.forms')),
            ],
            'timeseries' => $timeseries,
            'timeseries_previous' => (bool) $context->block->configValue('show_chart', true) ? ($previousSnapshot['timeseries'] ?? []) : [],
            'status_days' => $this->statusDays($snapshot['timeseries'] ?? []),
            'forms' => $forms,
            'insight' => $this->insight($current['forms.responses']['value'] ?? null, $previous['forms.responses']['value'] ?? null),
        ];
    }

    /**
     * Classify each day's response count for the activity strip, banding by the
     * busiest day so the strip reads relative to this site's own volume.
     *
     * @param  array<int, array<string, mixed>>  $timeseries
     * @return array<int, array{date: string, status: string}>
     */
    private function statusDays(array $timeseries): array
    {
        $max = 0;
        foreach ($timeseries as $day) {
            $max = max($max, (int) ($day['value'] ?? 0));
        }

        return array_map(function (array $day) use ($max): array {
            $value = (int) ($day['value'] ?? 0);

            $status = match (true) {
                $value <= 0 => 'none',
                $max > 0 && $value >= 0.6 * $max => 'busy',
                default => 'some',
            };

            return ['date' => (string) ($day['date'] ?? ''), 'status' => $status];
        }, $timeseries);
    }

    private function insight(?float $current, ?float $previous): ?string
    {
        if ($current === null) {
            return null;
        }

        $count = (int) $current;
        $sentence = ReportLang::get(
            $count === 1 ? 'form_responses.insight.singular' : 'form_responses.insight.plural',
            ['count' => Format::number($current)],
        );

        $change = Format::change($current, $previous);
        if ($change['percent'] !== null && $change['direction'] !== 'flat') {
            $sentence .= ReportLang::get('form_responses.insight.change', [
                'direction' => $change['direction'],
                'percent' => Format::number(abs($change['percent']), 1),
            ]);
        }

        return $sentence.'.';
    }
}
