<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

class TopDevicesBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.devices';
    }

    public function label(): string
    {
        return 'Top devices';
    }

    public function description(): string
    {
        return 'The devices visitors used during the period (desktop, mobile, tablet).';
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
            BlockOption::number('limit', 'Rows to show', 5, 3, 10),
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
            'devices' => array_slice($snapshot['devices'] ?? [], 0, $limit),
            'provider' => $snapshot['provider'] ?? null,
        ];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
