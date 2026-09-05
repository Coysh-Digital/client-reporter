<?php

declare(strict_types=1);

namespace App\Integrations\WordPress\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;

class CmsUpdatesBlock extends BlockType
{
    public function type(): string
    {
        return 'cms.updates';
    }

    public function label(): string
    {
        return 'Updates';
    }

    public function description(): string
    {
        return 'Outstanding WordPress core, plugin and theme updates.';
    }

    public function group(): string
    {
        return 'Website';
    }

    public function requiresIntegration(): ?string
    {
        return 'wordpress';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $reader = $context->reader;
        $snapshot = $reader->snapshot($context->site, 'wordpress', 'site', $context->range) ?? [];

        $applied = is_array($snapshot['updates_applied'] ?? null) ? $snapshot['updates_applied'] : [];
        $appliedCounts = ['core' => 0, 'plugin' => 0, 'theme' => 0];
        foreach ($applied as $entry) {
            $type = $entry['type'] ?? '';
            if (isset($appliedCounts[$type])) {
                $appliedCounts[$type]++;
            }
        }

        return [
            'core_update' => (bool) ($snapshot['core_update_available'] ?? false),
            'plugin_updates' => (int) ($reader->metricValue($context->site, 'wordpress', 'cms.plugin_updates', $context->range) ?? 0),
            'theme_updates' => (int) ($reader->metricValue($context->site, 'wordpress', 'cms.theme_updates', $context->range) ?? 0),
            'plugin_updates_list' => $snapshot['plugin_updates_list'] ?? [],
            'theme_updates_list' => $snapshot['theme_updates_list'] ?? [],
            'applied' => array_values($applied),
            'applied_total' => count($applied),
            'applied_counts' => $appliedCounts,
        ];
    }

    public function icon(): string
    {
        return 'wrench';
    }
}
