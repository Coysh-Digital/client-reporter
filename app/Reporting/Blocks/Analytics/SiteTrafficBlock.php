<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;
use Illuminate\Support\Str;

/**
 * A single, consolidated analytics component: headline metrics, a visitors
 * trend, and the top pages / referrers / countries / devices — everything the
 * separate analytics blocks show, in one panel. Reads the one analytics
 * "summary" snapshot, so it needs no extra collection.
 */
class SiteTrafficBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.site_traffic';
    }

    public function label(): string
    {
        return 'Site traffic';
    }

    public function description(): string
    {
        return 'Headline traffic metrics, a visitors trend, and top pages, referrers, countries and devices in one panel.';
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $current = $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->range);
        $previous = $compare && $context->comparison
            ? $context->reader->metricsForCategory($context->site, IntegrationCategory::Analytics, $context->comparison)
            : [];

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];

        $tile = fn (string $key, string $label, string $fmt, bool $goodUp): array => [
            'label' => $label,
            'fmt' => $fmt,
            'goodUp' => $goodUp,
            'current' => $current[$key]['value'] ?? null,
            'previous' => $previous[$key]['value'] ?? null,
        ];

        return [
            'has_data' => $current !== [],
            'provider' => $snapshot['provider'] ?? null,
            'summary' => $this->summary($current, $previous, $snapshot),
            'tiles' => [
                $tile('analytics.visitors', 'Visitors', 'number', true),
                $tile('analytics.visits', 'Visits', 'number', true),
                $tile('analytics.pageviews', 'Pageviews', 'number', true),
                $tile('analytics.visit_duration', 'Avg visit duration', 'duration', true),
            ],
            'bounce_rate' => $current['analytics.bounce_rate']['value'] ?? null,
            'timeseries' => $snapshot['timeseries'] ?? [],
            'top_pages' => array_slice($snapshot['top_pages'] ?? [], 0, 6),
            'sources' => array_slice($snapshot['sources'] ?? [], 0, 6),
            'countries' => array_slice($snapshot['countries'] ?? [], 0, 6),
            'devices' => array_slice($snapshot['devices'] ?? [], 0, 6),
            'events' => $snapshot['events'] ?? [],
        ];
    }

    /**
     * A deterministic plain-English summary from the resolved figures — the
     * headline visitor movement, plus the leading source and device.
     *
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $previous
     * @param  array<string, mixed>  $snapshot
     */
    private function summary(array $current, array $previous, array $snapshot): ?string
    {
        $visitors = $current['analytics.visitors']['value'] ?? null;
        if ($visitors === null) {
            return null;
        }

        $sentence = Format::number($visitors).' '.Str::plural('visitor', (int) $visitors).' this period';
        $change = Format::change($visitors, $previous['analytics.visitors']['value'] ?? null);
        if ($change['percent'] !== null && $change['direction'] !== 'flat') {
            $sentence .= ', '.$change['direction'].' '.Format::number(abs($change['percent']), 1).'% on the previous period';
        }
        $sentence .= '.';

        $topSource = $snapshot['sources'][0]['label'] ?? null;
        $topDevice = $snapshot['devices'][0]['label'] ?? null;
        if ($topSource !== null) {
            $extra = ($topSource !== '' ? $topSource : 'Direct').' was the largest source';
            if ($topDevice) {
                $extra .= ', with '.strtolower((string) $topDevice).' the most common device';
            }
            $sentence .= ' '.$extra.'.';
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'chart';
    }
}
