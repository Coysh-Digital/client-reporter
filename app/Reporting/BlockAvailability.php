<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Models\Site;
use App\Reporting\Contracts\BlockType;

/**
 * Decides which report blocks make sense for a site, from the integrations it
 * actually has live. Used to filter the builder's "add section" menu and to
 * skip unusable blocks when seeding a report from a template or default set.
 */
class BlockAvailability
{
    public function __construct(private readonly IntegrationRegistry $integrations) {}

    /**
     * Live integration keys for the site (connected or needing attention).
     *
     * @return array<int, string>
     */
    public function connectedKeys(Site $site): array
    {
        return $site->integrations()
            ->whereIn('status', [ConnectionStatus::Connected->value, ConnectionStatus::NeedsAttention->value])
            ->pluck('integration_key')
            ->all();
    }

    /**
     * @param  array<int, string>  $connectedKeys
     */
    public function isAvailable(BlockType $type, Site $site, array $connectedKeys): bool
    {
        $custom = $type->availableForSite($site);
        if ($custom !== null) {
            return $custom;
        }

        if ($key = $type->requiresIntegration()) {
            return in_array($key, $connectedKeys, true);
        }

        if ($category = $type->requiresCategory()) {
            return array_intersect($this->integrations->keysInCategory($category), $connectedKeys) !== [];
        }

        return true;
    }
}
