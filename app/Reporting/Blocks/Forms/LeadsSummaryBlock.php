<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Forms;

use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

/**
 * Provider-agnostic leads/signups summary. Reads from whichever Forms & Leads
 * integration the site has connected (Mailchimp today; form-plugin providers
 * later) via the shared leads.* metric layer.
 */
class LeadsSummaryBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'new_leads' => ['leads.new', 'New leads', 'number', true],
        'total' => ['leads.total', 'Total audience', 'number', true],
    ];

    public function type(): string
    {
        return 'forms.summary';
    }

    public function label(): string
    {
        return 'Leads & signups';
    }

    public function description(): string
    {
        return 'New leads, form submissions or email signups for the period, versus the previous period.';
    }

    public function group(): string
    {
        return 'Forms & Leads';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Forms;
    }

    /**
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'new_leads' => 'New leads',
                'total' => 'Total audience',
            ], ['new_leads', 'total']),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this month\'s leads and signups in two to three sentences for a '
            .'non-technical client. Cover new leads and the total audience versus the prior '
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
        $selected = (array) $context->block->configValue('metrics', array_keys(self::METRICS));

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Forms, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Forms, $context->comparison)
            : [];

        $connection = $context->reader->connectionForCategory($context->site, IntegrationCategory::Forms);
        $provider = $connection
            ? app(IntegrationRegistry::class)->find($connection->integration_key)?->manifest()->name
            : null;

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
            'provider' => $provider,
            'metrics' => $metrics,
            'insight' => $this->insight($current),
        ];
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     */
    private function insight(array $current): ?string
    {
        $new = $current['leads.new']['value'] ?? null;
        if ($new === null) {
            return null;
        }

        $count = (int) $new;

        return $count.' new '.($count === 1 ? 'lead was' : 'leads were').' captured this period.';
    }

    public function icon(): string
    {
        return 'chart';
    }
}
