<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

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

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];

        return ['timeseries' => $snapshot['timeseries'] ?? [], 'provider' => $snapshot['provider'] ?? null];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
