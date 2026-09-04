<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

class TopCountriesBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.countries';
    }

    public function label(): string
    {
        return 'Top countries';
    }

    public function description(): string
    {
        return 'Where in the world visitors came from during the period.';
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
            BlockOption::number('limit', 'Rows to show', 5, 3, 25),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Analytics, 'summary', $context->range) ?? [];
        $limit = (int) $context->block->configValue('limit', 5);

        return [
            'countries' => array_slice($snapshot['countries'] ?? [], 0, $limit),
            'provider' => $snapshot['provider'] ?? null,
        ];
    }

    public function icon(): string
    {
        return 'globe';
    }
}
