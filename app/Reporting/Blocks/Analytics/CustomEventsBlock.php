<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

/**
 * Custom event counts (goals, conversions, tracked interactions) for the
 * period. An empty list here means no events were recorded during the period
 * — every bundled analytics provider (GA4, Plausible, Fathom, Matomo, Umami)
 * supports some form of custom event tracking.
 */
class CustomEventsBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.events';
    }

    public function label(): string
    {
        return 'Custom events';
    }

    public function description(): string
    {
        return 'Custom event or goal counts for the period, where the connected analytics provider supports them.';
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
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [
            BlockOption::number('limit', 'Rows to show', 8, 3, 25),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];
        $limit = (int) $context->block->configValue('limit', 8);

        return [
            'events' => array_slice($snapshot['events'] ?? [], 0, $limit),
            'provider' => $snapshot['provider'] ?? null,
        ];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
