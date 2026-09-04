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
     * Where a site's ecommerce data lives. Store metrics either ride on a CMS
     * integration (WooCommerce under WordPress, Craft Commerce under Craft) or
     * come from a standalone store platform (Shopify), so the generic Store
     * block asks here rather than hard-coding a provider.
     *
     * @return array{integration_key: string, collector_key: string, provider: string}|null
     */
    public function ecommerceSource(Site $site): ?array
    {
        $sources = [
            // Direct WooCommerce REST connection is preferred over the same
            // store's data arriving via the WordPress connector.
            ['integration_key' => 'woocommerce', 'collector_key' => 'sales', 'provider' => 'WooCommerce'],
            ['integration_key' => 'wordpress', 'collector_key' => 'woocommerce', 'provider' => 'WooCommerce'],
            ['integration_key' => 'craft', 'collector_key' => 'commerce', 'provider' => 'Craft Commerce'],
            ['integration_key' => 'shopify', 'collector_key' => 'shopify', 'provider' => 'Shopify'],
            ['integration_key' => 'stripe', 'collector_key' => 'stripe', 'provider' => 'Stripe'],
        ];

        $connected = $site->integrations()->pluck('integration_key')->all();

        foreach ($sources as $source) {
            if (in_array($source['integration_key'], $connected, true)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * The site's connection for an integration (first live one wins).
     */
    public function connectionFor(Site $site, string $integrationKey): ?SiteIntegration
    {
        return $site->integrations()
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
        $keys = app(IntegrationRegistry::class)->keysInCategory($category);

        if ($keys === []) {
            return null;
        }

        return $site->integrations()
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
