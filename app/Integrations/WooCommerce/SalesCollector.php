<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;

/**
 * Collects WooCommerce sales through the store's own REST API into the shared
 * ecommerce.* metric layer plus a 'sales' snapshot (currency + top products) —
 * the same shape the other store sources emit, so the generic Store block
 * renders it.
 */
class SalesCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'sales';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new WooCommerceRestClient(
            (string) $connection->setting('store_url'),
            (string) $connection->credential('consumer_key'),
            (string) $connection->credential('consumer_secret'),
        );

        $sales = $client->salesReport($range);
        $currency = $client->currency();

        $revenue = (float) ($sales['total_sales'] ?? 0);
        $orders = (int) ($sales['total_orders'] ?? 0);
        $items = (int) ($sales['total_items'] ?? 0);
        $refunds = (float) ($sales['total_refunds'] ?? 0);

        // Top sellers expose quantity but not per-product revenue; leave revenue
        // unset so the report shows "—" rather than a misleading zero.
        $topProducts = array_map(fn (array $row): array => [
            'name' => (string) ($row['title'] ?? $row['name'] ?? 'Product'),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'revenue' => null,
        ], $client->topSellers($range));

        return CollectorResult::make()
            ->metric('ecommerce.revenue', round($revenue, 2), $currency)
            ->metric('ecommerce.orders', $orders)
            ->metric('ecommerce.aov', $orders > 0 ? round($revenue / $orders, 2) : 0, $currency)
            ->metric('ecommerce.items_sold', $items)
            ->metric('ecommerce.refunds', round($refunds, 2), $currency)
            ->snapshot([
                'active' => true,
                'currency' => $currency,
                'top_products' => array_slice($topProducts, 0, 10),
            ]);
    }
}
