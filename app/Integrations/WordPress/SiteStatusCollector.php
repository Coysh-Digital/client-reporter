<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects WordPress core/theme/plugin status and update availability. Reports
 * updates — it never performs them.
 */
class SiteStatusCollector extends AbstractCollector
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
        );

        $data = $client->get('site');

        $coreUpdate = (bool) ($data['core_update_available'] ?? false);
        $pluginUpdates = (int) ($data['plugin_updates'] ?? 0);
        $themeUpdates = (int) ($data['theme_updates'] ?? 0);

        return CollectorResult::make()
            ->granularity('point')
            ->metric('cms.core_update_available', $coreUpdate ? 1 : 0)
            ->metric('cms.plugin_updates', $pluginUpdates)
            ->metric('cms.theme_updates', $themeUpdates)
            ->metric('cms.updates_total', ($coreUpdate ? 1 : 0) + $pluginUpdates + $themeUpdates)
            ->metric('cms.users', (int) ($data['users'] ?? 0))
            ->metric('cms.admins', (int) ($data['admins'] ?? 0))
            ->snapshot([
                'wordpress_version' => $data['wordpress_version'] ?? null,
                'php_version' => $data['php_version'] ?? null,
                'site_name' => $data['site_name'] ?? null,
                'environment' => $data['environment'] ?? null,
                'active_theme' => $data['active_theme'] ?? null,
                'core_update_available' => $coreUpdate,
                'plugin_updates_list' => $data['plugin_updates_list'] ?? [],
                'theme_updates_list' => $data['theme_updates_list'] ?? [],
                'site_health' => $data['site_health'] ?? null,
                'plugins_total' => $data['plugins_total'] ?? null,
            ]);
    }
}
