<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Reads stored, period-scoped metrics and snapshots for report blocks. Blocks
 * never call external APIs directly; they read here, so reports load quickly and
 * remain available even when a service is temporarily down.
 */
class MetricReader
{
    /**
     * Connections looked up so far, keyed by site and integration/category. A
     * report resolves dozens of blocks against the same handful of
     * connections; without this each block re-queried site_integrations.
     *
     * @var array<string, SiteIntegration|null>
     */
    private array $connections = [];

    public function __construct(private readonly IntegrationRegistry $integrations) {}

    /**
     * Where a site's ecommerce data lives. Store metrics either ride on a CMS
     * integration (WooCommerce under WordPress, Craft Commerce under Craft) or
     * come from a standalone store platform, so the generic Store block asks
     * here. Each integration declares itself via {@see Integration::providesEcommerce()};
     * the highest-priority connected one wins.
     *
     * @return array{integration_key: string, collector_key: string, provider: string}|null
     */
    public function ecommerceSource(Site $site): ?array
    {
        $connected = $site->integrations()->pluck('integration_key')->all();
        $best = null;

        foreach ($this->integrations->all() as $integration) {
            $source = $integration->providesEcommerce();

            if ($source === null || ! in_array($integration->key(), $connected, true)) {
                continue;
            }

            if ($best === null || $source['priority'] > $best['priority']) {
                $best = $source + ['integration_key' => $integration->key()];
            }
        }

        return $best === null ? null : [
            'integration_key' => $best['integration_key'],
            'collector_key' => $best['collector_key'],
            'provider' => $best['provider'],
        ];
    }

    /**
     * The site's connection for an integration (first live one wins).
     */
    public function connectionFor(Site $site, string $integrationKey): ?SiteIntegration
    {
        return $this->connections["{$site->id}:key:{$integrationKey}"] ??= $site->integrations()
            ->where('integration_key', $integrationKey)
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [ConnectionStatus::Connected->value])
            ->first();
    }

    /**
     * Normalised metric values for a site's integration over a period, keyed by
     * metric_key.
     *
     * @return array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>
     */
    public function metrics(Site $site, string $integrationKey, DateRange $range): array
    {
        $connection = $this->connectionFor($site, $integrationKey);

        if ($connection === null) {
            return [];
        }

        return Metric::query()
            ->where('site_integration_id', $connection->id)
            ->whereDate('period_start', $range->start->toDateString())
            ->whereDate('period_end', $range->end->toDateString())
            ->get()
            ->mapWithKeys(fn (Metric $m): array => [
                $m->metric_key => [
                    'value' => (float) $m->value,
                    'unit' => $m->unit,
                    'meta' => $m->meta ?? [],
                ],
            ])
            ->all();
    }

    public function metricValue(Site $site, string $integrationKey, string $metricKey, DateRange $range): ?float
    {
        return $this->metrics($site, $integrationKey, $range)[$metricKey]['value'] ?? null;
    }

    /**
     * The site's connection for the first integration in a category (e.g. any
     * connected analytics provider). Returns the integration key too, so the
     * caller knows which provider produced the data.
     */
    public function connectionForCategory(Site $site, IntegrationCategory $category): ?SiteIntegration
    {
        $cacheKey = "{$site->id}:category:{$category->value}";

        if (array_key_exists($cacheKey, $this->connections)) {
            return $this->connections[$cacheKey];
        }

        $keys = $this->integrations->keysInCategory($category);

        return $this->connections[$cacheKey] = $keys === [] ? null : $site->integrations()
            ->whereIn('integration_key', $keys)
            ->orderByRaw('CASE status WHEN ? THEN 0 ELSE 1 END', [ConnectionStatus::Connected->value])
            ->first();
    }

    /**
     * @return array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>
     */
    public function metricsForCategory(Site $site, IntegrationCategory $category, DateRange $range): array
    {
        $connection = $this->connectionForCategory($site, $category);

        return $connection ? $this->metrics($site, $connection->integration_key, $range) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshotForCategory(Site $site, IntegrationCategory $category, string $collectorKey, DateRange $range): ?array
    {
        $connection = $this->connectionForCategory($site, $category);

        return $connection ? $this->snapshot($site, $connection->integration_key, $collectorKey, $range) : null;
    }

    /**
     * A collector's snapshot payload for a site's integration over a period.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(Site $site, string $integrationKey, string $collectorKey, DateRange $range): ?array
    {
        $connection = $this->connectionFor($site, $integrationKey);

        if ($connection === null) {
            return null;
        }

        $snapshot = MetricSnapshot::query()
            ->where('site_integration_id', $connection->id)
            ->where('collector_key', $collectorKey)
            ->whereDate('period_start', $range->start->toDateString())
            ->whereDate('period_end', $range->end->toDateString())
            ->first();

        return $snapshot?->payload;
    }
}
