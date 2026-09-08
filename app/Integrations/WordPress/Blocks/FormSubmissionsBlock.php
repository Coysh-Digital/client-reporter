<?php

declare(strict_types=1);

namespace App\Integrations\WordPress\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\ReportLang;

/**
 * Website form submissions for the period — Gravity Forms and/or Ninja Forms,
 * collected through the WordPress connector. A period total (versus the
 * previous period), a per-form breakdown and a daily trend. Kept separate from
 * the email-provider "Leads & signups" section so website enquiries and
 * email-list growth are never conflated in one figure.
 */
class FormSubmissionsBlock extends BlockType
{
    public function type(): string
    {
        return 'wordpress.forms';
    }

    public function label(): string
    {
        return ReportLang::get('forms.heading');
    }

    public function description(): string
    {
        return 'Website form submissions for the period (Gravity Forms, Ninja Forms), with a per-form breakdown.';
    }

    public function group(): string
    {
        return 'Forms & Leads';
    }

    public function icon(): string
    {
        return 'envelope';
    }

    public function requiresIntegration(): ?string
    {
        return 'wordpress';
    }

    public function canBeEmpty(): bool
    {
        return true;
    }

    /**
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::toggle('show_chart', 'Show daily submissions chart', true),
            BlockOption::number('form_limit', 'Forms to list', 8, 1, 30),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this period\'s website form submissions in two to three sentences for '
            .'a non-technical client. Cover the total submissions versus the previous period and '
            .'which forms drove them. Use only the figures provided.';
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

        return array_filter([
            'total_submissions' => $resolved['total'] ?? null,
            'previous_submissions' => $resolved['previous'] ?? null,
            'forms' => array_map(
                fn (array $form): array => ['name' => $form['name'] ?? '', 'submissions' => $form['submissions'] ?? 0],
                $resolved['forms'] ?? [],
            ),
        ], fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);
        $limit = (int) $context->block->configValue('form_limit', 8);

        $snapshot = $context->reader->snapshot($context->site, 'wordpress', 'forms', $context->range) ?? [];

        $total = $context->reader->metricValue($context->site, 'wordpress', 'forms.submissions', $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricValue($context->site, 'wordpress', 'forms.submissions', $context->comparison)
            : null;

        $allForms = array_values((array) ($snapshot['forms'] ?? []));
        $forms = array_slice($allForms, 0, max(1, $limit));

        $active = ($snapshot['active'] ?? false) === true;
        $hasData = $active && ((int) ($total ?? 0) > 0 || $allForms !== []);

        return [
            'has_data' => $hasData,
            'total' => $total !== null ? (int) $total : null,
            'previous' => $previous !== null ? (int) $previous : null,
            'metrics' => [
                ['label' => ReportLang::get('forms.metric.submissions'), 'fmt' => 'number', 'goodUp' => true, 'current' => $total !== null ? (float) $total : null, 'previous' => $previous !== null ? (float) $previous : null],
                ['label' => ReportLang::get('forms.metric.forms'), 'fmt' => 'number', 'goodUp' => true, 'current' => (float) count($allForms), 'previous' => null],
            ],
            'forms' => $forms,
            'timeseries' => (bool) $context->block->configValue('show_chart', true) ? array_values((array) ($snapshot['timeseries'] ?? [])) : [],
            'insight' => $this->insight($total, count($allForms)),
        ];
    }

    private function insight(int|float|null $total, int $formCount): ?string
    {
        if ($total === null || (int) $total <= 0) {
            return null;
        }

        $count = (int) $total;

        return ReportLang::get(
            $count === 1 ? 'forms.insight.singular' : 'forms.insight.plural',
            ['count' => $count, 'forms' => $formCount],
        );
    }
}
