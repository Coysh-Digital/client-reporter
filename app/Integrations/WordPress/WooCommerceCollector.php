<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects WooCommerce sales for the reporting period through the WordPress
 * connector. Produces client-friendly ecommerce figures, not operational data.
 */
class WooCommerceCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'woocommerce';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new SignedConnectorClient(
            (string) $connection->setting('base_url'),
            (string) $connection->credential('secret'),
        );

        $data = $client->get('woocommerce', [
            'start' => $range->start->toDateString(),
            'end' => $range->end->toDateString(),
        ]);

        $result = CollectorResult::make();

        // WooCommerce not installed/active — nothing to report for this site.
        if (($data['active'] ?? false) !== true) {
            return $result->snapshot(['active' => false]);
        }

        return $result
            ->metric('ecommerce.revenue', (float) ($data['revenue'] ?? 0), $data['currency'] ?? null)
            ->metric('ecommerce.orders', (int) ($data['orders'] ?? 0))
            ->metric('ecommerce.aov', (float) ($data['average_order_value'] ?? 0), $data['currency'] ?? null)
            ->metric('ecommerce.items_sold', (int) ($data['items_sold'] ?? 0))
            ->metric('ecommerce.refunds', (float) ($data['refunds'] ?? 0), $data['currency'] ?? null)
            ->snapshot([
                'active' => true,
                'currency' => $data['currency'] ?? null,
                'top_products' => $data['top_products'] ?? [],
            ]);
    }
}
