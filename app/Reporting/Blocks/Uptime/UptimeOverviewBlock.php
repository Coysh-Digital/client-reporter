<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;
use App\Support\ReportLang;

/**
 * A consolidated uptime & performance component: headline availability metrics,
 * a daily uptime strip and trend, the Lighthouse performance score, and the
 * period's incidents — in one panel. Reads whatever the site's monitoring (and,
 * if connected, performance) integrations collected; panels with no data are
 * omitted rather than shown empty.
 */
class UptimeOverviewBlock extends BlockType
{
    /**
     * Lighthouse category metric key => label, in display order. A method rather
     * than a const so the labels resolve through the report language dictionary.
     *
     * @return array<string, string>
     */
    private static function lighthouse(): array
    {
        return [
            'performance.score' => ReportLang::get('lighthouse.performance'),
            'performance.accessibility' => ReportLang::get('lighthouse.accessibility'),
            'performance.best_practices' => ReportLang::get('lighthouse.best_practices'),
            'performance.seo' => ReportLang::get('lighthouse.seo'),
        ];
    }

    public function type(): string
    {
        return 'uptime.overview';
    }

    public function label(): string
    {
        return ReportLang::get('uptime_overview.heading');
    }

    public function description(): string
    {
        return 'Availability, response time, a daily uptime strip, the Lighthouse performance score and incidents in one panel.';
    }

    public function group(): string
    {
        return 'Uptime';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Monitoring;
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::number('incident_limit', 'Incidents to show', 10, 1, 50),
            BlockOption::toggle('ai_summary', 'AI summary', false, 'Add an AI-written paragraph summarising this section (requires AI configured in Settings).'),
        ];
    }

    public function supportsAiSummary(): bool
    {
        return true;
    }

    public function defaultAiPrompt(): ?string
    {
        return 'Summarise the website\'s uptime and performance for the month in two to three '
            .'sentences for a non-technical client. Mention availability, any incidents, and the '
            .'Lighthouse performance score. Use only the figures provided.';
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

        $lighthouse = [];
        foreach ($resolved['lighthouse'] ?? [] as $entry) {
            $lighthouse[$entry['label']] = $entry['score'];
        }

        return array_filter([
            'metrics' => $metrics,
            'lighthouse' => $lighthouse,
            'incident_count' => count($resolved['incidents'] ?? []),
        ], fn ($value): bool => $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Monitoring, $context->comparison)
            : [];

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [];

        $tile = fn (string $metricKey, string $label, string $fmt, bool $goodUp): array => [
            'label' => $label,
            'fmt' => $fmt,
            'goodUp' => $goodUp,
            'current' => $current[$metricKey]['value'] ?? null,
            'previous' => $previous[$metricKey]['value'] ?? null,
        ];

        $tiles = [
            $tile('uptime.percentage', ReportLang::get('uptime.tile.uptime'), 'uptime', true),
            $tile('uptime.response_time_ms', ReportLang::get('uptime.tile.response'), 'ms', false),
            $tile('uptime.incidents', ReportLang::get('uptime.tile.incidents'), 'number', false),
            $tile('uptime.downtime_seconds', ReportLang::get('uptime.tile.downtime'), 'duration', false),
        ];
        // A cert-alerts tile where the provider reports certificate expiry
        // (Uptime Kuma); otherwise fall back to the monitor count.
        $tiles[] = isset($current['uptime.cert_alerts'])
            ? $tile('uptime.cert_alerts', ReportLang::get('uptime.tile.cert_alerts'), 'number', false)
            : $tile('uptime.monitors', ReportLang::get('uptime.tile.monitors'), 'number', true);

        $limit = (int) $context->block->configValue('incident_limit', 10);

        // Lighthouse scores, only for whichever a connected performance
        // integration collected (accessibility/best-practices/SEO are lab-only).
        $performance = $context->reader->metricsForCategory($context->site, IntegrationCategory::Performance, $context->range);
        $lighthouse = [];
        foreach (self::lighthouse() as $metricKey => $label) {
            $value = $performance[$metricKey]['value'] ?? null;
            if ($value !== null) {
                $lighthouse[] = ['label' => $label, 'score' => (int) round($value), 'rating' => $this->rating((int) round($value))];
            }
        }

        $performanceSnapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Performance, 'core-web-vitals', $context->range) ?? [];

        return [
            'has_data' => $current !== [],
            'summary' => $this->summary($current, $performance['performance.score']['value'] ?? null),
            'tiles' => $tiles,
            'timeseries' => $snapshot['timeseries'] ?? [],
            'status_days' => $this->statusDays($snapshot['timeseries'] ?? []),
            'incidents' => array_slice($snapshot['incidents'] ?? [], 0, $limit),
            'lighthouse' => $lighthouse,
            'lighthouse_history' => $performanceSnapshot['timeseries'] ?? [],
        ];
    }

    /**
     * Classify each day's uptime percentage for the status strip.
     *
     * @param  array<int, array<string, mixed>>  $timeseries
     * @return array<int, array{date: string, status: string}>
     */
    private function statusDays(array $timeseries): array
    {
        return array_map(function (array $day): array {
            $value = (float) ($day['value'] ?? 0);

            $status = match (true) {
                $value <= 0 => 'none',      // no checks recorded that day
                $value >= 99.9 => 'healthy',
                $value >= 99.5 => 'partial',
                default => 'below',
            };

            return ['date' => (string) ($day['date'] ?? ''), 'status' => $status];
        }, $timeseries);
    }

    private function rating(int $score): string
    {
        return match (true) {
            $score >= 90 => 'good',
            $score >= 50 => 'needs-improvement',
            default => 'poor',
        };
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     */
    private function summary(array $current, ?float $score): ?string
    {
        $uptime = $current['uptime.percentage']['value'] ?? null;
        if ($uptime === null) {
            return null;
        }

        $sentence = ReportLang::get('uptime.summary.base', ['uptime' => Format::percent($uptime, 2)]);

        $response = $current['uptime.response_time_ms']['value'] ?? null;
        if ($response !== null) {
            $sentence .= ReportLang::get('uptime.summary.response', ['response' => Format::forType($response, 'ms')]);
        } else {
            $sentence .= ReportLang::get('uptime.summary.no_response');
        }

        $incidents = (int) ($current['uptime.incidents']['value'] ?? 0);
        if ($incidents > 0) {
            $sentence .= ReportLang::get(
                $incidents === 1 ? 'uptime.summary.incident_singular' : 'uptime.summary.incident_plural',
                ['count' => $incidents],
            );
        }

        if ($score !== null) {
            $sentence .= ReportLang::get('uptime.summary.lighthouse', ['score' => (int) round($score)]);
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
