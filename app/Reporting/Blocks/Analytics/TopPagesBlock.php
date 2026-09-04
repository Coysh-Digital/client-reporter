<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

class TopPagesBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.top_pages';
    }

    public function label(): string
    {
        return 'Top pages';
    }

    public function description(): string
    {
        return 'The most-viewed pages during the period.';
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

        return ['pages' => array_slice($snapshot['top_pages'] ?? [], 0, $limit), 'provider' => $snapshot['provider'] ?? null];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
