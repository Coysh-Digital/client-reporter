<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

/**
 * A client-friendly list of outages during the period, from whichever
 * monitoring integration the site has connected.
 */
class IncidentsBlock extends BlockType
{
    public function type(): string
    {
        return 'uptime.incidents';
    }

    public function label(): string
    {
        return 'Incidents';
    }

    public function description(): string
    {
        return 'A client-friendly list of outages during the period.';
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
            BlockOption::number('limit', 'Incidents to show', 10, 1, 50),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $payload = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [];
        $limit = (int) $context->block->configValue('limit', 10);

        return [
            'incidents' => array_slice($payload['incidents'] ?? [], 0, $limit),
            'monitors' => $payload['monitors'] ?? [],
        ];
    }

    public function icon(): string
    {
        return 'pulse';
    }
}
