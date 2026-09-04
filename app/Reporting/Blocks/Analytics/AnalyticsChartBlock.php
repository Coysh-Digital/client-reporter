<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

class AnalyticsChartBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.chart';
    }

    public function label(): string
    {
        return 'Visitors chart';
    }

    public function description(): string
    {
        return 'A daily visitors chart for the period.';
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
            BlockOption::toggle('compare', 'Overlay the previous period', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $compare = (bool) $context->block->configValue('compare', true);

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];
        $previousSnapshot = $compare && $context->comparison
            ? ($context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->comparison) ?? [])
            : [];

        return [
            'timeseries' => $snapshot['timeseries'] ?? [],
            'timeseries_previous' => $previousSnapshot['timeseries'] ?? [],
            'provider' => $snapshot['provider'] ?? null,
        ];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
