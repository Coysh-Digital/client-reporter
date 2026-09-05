<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Analytics;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\ReportLang;

class TrafficSourcesBlock extends BlockType
{
    public function type(): string
    {
        return 'analytics.sources';
    }

    public function label(): string
    {
        return ReportLang::get('sources.label');
    }

    public function description(): string
    {
        return 'Where visitors came from during the period.';
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

        return ['sources' => array_slice($snapshot['sources'] ?? [], 0, $limit), 'provider' => $snapshot['provider'] ?? null];
    }

    public function icon(): string
    {
        return 'chart';
    }
}
