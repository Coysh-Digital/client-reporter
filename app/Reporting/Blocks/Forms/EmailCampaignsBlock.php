<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Forms;

use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\ReportLang;
use Carbon\CarbonImmutable;

/**
 * Email campaign performance from a connected email-marketing integration
 * (Mailchimp, EmailOctopus): campaigns sent, open and click rates for the
 * period, and a table of the individual campaigns. Provider-agnostic — reads
 * the shared email.* metrics and the campaigns snapshot.
 */
class EmailCampaignsBlock extends BlockType
{
    /**
     * key => [metric_key, label, fmt, goodUp]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private static function metrics(): array
    {
        return [
            'campaigns_sent' => ['email.campaigns_sent', ReportLang::get('email.metric.campaigns_sent'), 'number', true],
            'open_rate' => ['email.open_rate', ReportLang::get('email.metric.open_rate'), 'percent1', true],
            'click_rate' => ['email.click_rate', ReportLang::get('email.metric.click_rate'), 'percent1', true],
            'recipients' => ['email.recipients', ReportLang::get('email.metric.recipients'), 'number', true],
            'unsubscribed' => ['email.unsubscribed', ReportLang::get('email.metric.unsubscribed'), 'number', false],
        ];
    }

    public function type(): string
    {
        return 'email.campaigns';
    }

    public function label(): string
    {
        return ReportLang::get('email.heading');
    }

    public function description(): string
    {
        return 'Email campaigns sent this period with open and click rates, and a table of each campaign.';
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
                'campaigns_sent' => 'Campaigns sent',
                'open_rate' => 'Open rate',
                'click_rate' => 'Click rate',
                'recipients' => 'Recipients',
                'unsubscribed' => 'Unsubscribes',
            ], ['campaigns_sent', 'open_rate', 'click_rate']),
            BlockOption::toggle('show_table', 'Show campaign table', true),
            BlockOption::number('campaigns_limit', 'Campaigns to list', 8, 1, 20),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise this period\'s email campaign performance in two to three sentences '
            .'for a non-technical client. Cover how many campaigns were sent and the open and '
            .'click rates. Use only the figures provided.';
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
        $selected = (array) $context->block->configValue('metrics', ['campaigns_sent', 'open_rate', 'click_rate']);

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Forms, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Forms, $context->comparison)
            : [];
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Forms, 'summary', $context->range) ?? [];

        $campaigns = is_array($snapshot['campaigns'] ?? null) ? $snapshot['campaigns'] : [];

        $connection = $context->reader->connectionForCategory($context->site, IntegrationCategory::Forms);
        $provider = $connection
            ? app(IntegrationRegistry::class)->find($connection->integration_key)?->manifest()->name
            : null;

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

        $rows = [];
        if ((bool) $context->block->configValue('show_table', true)) {
            $limit = (int) $context->block->configValue('campaigns_limit', 8);
            foreach (array_slice($campaigns, 0, $limit) as $campaign) {
                $sentAt = (string) ($campaign['sent_at'] ?? '');
                $rows[] = [
                    'name' => (string) ($campaign['name'] ?? 'Campaign'),
                    'sent_at' => $sentAt !== '' ? CarbonImmutable::parse($sentAt)->format('j M Y') : '—',
                    'recipients' => (int) ($campaign['recipients'] ?? 0),
                    'opens' => (int) ($campaign['opens'] ?? 0),
                    'clicks' => (int) ($campaign['clicks'] ?? 0),
                    'open_rate' => (float) ($campaign['open_rate'] ?? 0),
                    'click_rate' => (float) ($campaign['click_rate'] ?? 0),
                    'unsubscribed' => $campaign['unsubscribed'] ?? null,
                ];
            }
        }

        $count = count($campaigns);

        return [
            'has_data' => $count > 0,
            'provider' => $provider,
            'metrics' => $metrics,
            'campaigns' => $rows,
            'insight' => $count > 0
                ? ReportLang::get($count === 1 ? 'email.insight.singular' : 'email.insight.plural', ['count' => $count])
                : null,
        ];
    }

    public function icon(): string
    {
        return 'envelope';
    }
}
