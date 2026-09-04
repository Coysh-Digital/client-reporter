<?php

declare(strict_types=1);

namespace App\Integrations\Craft\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class CraftUpdatesBlock extends BlockType
{
    public function type(): string
    {
        return 'craft.updates';
    }

    public function label(): string
    {
        return 'Craft updates';
    }

    public function description(): string
    {
        return 'Outstanding Craft and plugin updates.';
    }

    public function group(): string
    {
        return 'Website';
    }

    public function requiresIntegration(): ?string
    {
        return 'craft';
    }

    public function view(): string
    {
        return 'reports.blocks.cms.updates';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $reader = $context->reader;
        $snapshot = $reader->snapshot($context->site, 'craft', 'site', $context->range) ?? [];

        return [
            'core_update' => (bool) ($snapshot['craft_update_available'] ?? false),
            'plugin_updates' => (int) ($reader->metricValue($context->site, 'craft', 'cms.plugin_updates', $context->range) ?? 0),
            'theme_updates' => 0,
            'plugin_updates_list' => $snapshot['plugin_updates_list'] ?? [],
            'theme_updates_list' => [],
        ];
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
