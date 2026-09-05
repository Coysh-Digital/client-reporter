<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects WordPress core/theme/plugin status and update availability, plus the
 * history of updates *applied* during the period. Reports updates — it never
 * performs them.
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

        $applied = $this->appliedUpdates($client, $connection, $range, $data);

        return CollectorResult::make()
            ->granularity('point')
            ->metric('cms.core_update_available', $coreUpdate ? 1 : 0)
            ->metric('cms.plugin_updates', $pluginUpdates)
            ->metric('cms.theme_updates', $themeUpdates)
            ->metric('cms.updates_total', ($coreUpdate ? 1 : 0) + $pluginUpdates + $themeUpdates)
            ->metric('cms.updates_applied', count($applied['entries']))
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
                'updates_applied' => $applied['entries'],
                'updates_applied_source' => $applied['source'],
                'site_health' => $data['site_health'] ?? null,
                'plugins_total' => $data['plugins_total'] ?? null,
            ]);
    }

    /**
     * The updates applied during the period. Prefers the connector's own log
     * (accurate, per plugin/theme/core); falls back to inferring a core-version
     * change from the previous snapshot when the connector predates that
     * endpoint.
     *
     * @param  array<string, mixed>  $data  the current /site payload
     * @return array{entries: array<int, array<string, mixed>>, source: string}
     */
    private function appliedUpdates(SignedConnectorClient $client, SiteIntegration $connection, DateRange $range, array $data): array
    {
        try {
            $log = $client->get('updates', [
                'from' => $range->start->toDateString(),
                'to' => $range->end->toDateString(),
            ]);
            $entries = is_array($log['entries'] ?? null) ? array_values($log['entries']) : [];

            return ['entries' => $entries, 'source' => 'log'];
        } catch (IntegrationException) {
            // Older connector without the /updates endpoint — fall back below.
        }

        return ['entries' => $this->inferCoreChange($connection, $data), 'source' => 'inferred'];
    }

    /**
     * A best-effort fallback: if the WordPress version has changed since the
     * last snapshot, record that core was updated. Only core/theme are visible
     * to inference — per-plugin history needs the connector's log.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function inferCoreChange(SiteIntegration $connection, array $data): array
    {
        /** @var ?MetricSnapshot $previous */
        $previous = $connection->snapshots()
            ->where('collector_key', 'site')
            ->latest('captured_at')
            ->first();

        if ($previous === null) {
            return [];
        }

        $entries = [];
        $newVersion = $data['wordpress_version'] ?? null;
        $oldVersion = $previous->payload['wordpress_version'] ?? null;

        if ($newVersion !== null && $oldVersion !== null && $newVersion !== $oldVersion) {
            $entries[] = [
                'type' => 'core',
                'name' => 'WordPress core',
                'version' => (string) $newVersion,
                'date' => now()->toIso8601String(),
            ];
        }

        return $entries;
    }
}
