<?php

declare(strict_types=1);

namespace App\Integrations\Craft;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects Craft CMS version, update availability, plugin status and queue
 * health. Reports updates — it never applies them.
 */
class CraftStatusCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'site';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new SignedConnectorClient(
            (string) $connection->setting('base_url'),
            (string) $connection->credential('secret'),
            CraftIntegration::PATH_PREFIX,
        );

        $data = $client->get('site');

        $coreUpdate = (bool) ($data['craft_update_available'] ?? false);
        $pluginUpdates = (int) ($data['plugin_updates'] ?? 0);

        return CollectorResult::make()
            ->granularity('point')
            ->metric('cms.core_update_available', $coreUpdate ? 1 : 0)
            ->metric('cms.plugin_updates', $pluginUpdates)
            ->metric('cms.updates_total', ($coreUpdate ? 1 : 0) + $pluginUpdates)
            ->metric('cms.queue_failed', (int) ($data['queue_failed'] ?? 0))
            ->snapshot([
                'craft_version' => $data['craft_version'] ?? null,
                'php_version' => $data['php_version'] ?? null,
                'environment' => $data['environment'] ?? null,
                'craft_update_available' => $coreUpdate,
                'plugin_updates_list' => $data['plugin_updates_list'] ?? [],
                'queue_pending' => (int) ($data['queue_pending'] ?? 0),
                'queue_failed' => (int) ($data['queue_failed'] ?? 0),
                'licence' => $data['licence'] ?? null,
            ]);
    }
}
