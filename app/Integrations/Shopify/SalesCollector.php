<?php

declare(strict_types=1);

namespace App\Integrations\Shopify;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects Shopify sales into the shared ecommerce.* metric layer plus a
 * 'shopify' snapshot (currency + top products) — the same shape WooCommerce and
 * Craft Commerce emit, so the generic Store block renders any of them.
 */
class SalesCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'shopify';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new ShopifyClient(
            (string) $connection->setting('shop_domain'),
            (string) $connection->credential('access_token'),
        );

        $orders = $client->orders($range);

        $result = CollectorResult::make();

        if ($orders === []) {
            return $result
                ->metric('ecommerce.revenue', 0)
                ->metric('ecommerce.orders', 0)
                ->metric('ecommerce.aov', 0)
                ->metric('ecommerce.items_sold', 0)
                ->snapshot(['active' => true, 'currency' => null, 'top_products' => [], 'timeseries' => $this->buildTimeseries([], $range)]);
        }

        $revenue = 0.0;
        $refunds = 0.0;
        $itemsSold = 0;
        $currency = null;

        /** @var array<string, array{name: string, quantity: int, revenue: float}> $products */
        $products = [];

        /** @var array<string, float> $byDay */
        $byDay = [];

        foreach ($orders as $order) {
            $orderRevenue = (float) ($order['total_price'] ?? 0);
            $revenue += $orderRevenue;
            $refunds += $this->refundTotal($order);
            $currency ??= isset($order['currency']) ? (string) $order['currency'] : null;

            if (isset($order['created_at'])) {
                $day = CarbonImmutable::parse((string) $order['created_at'])->toDateString();
                $byDay[$day] = ($byDay[$day] ?? 0.0) + $orderRevenue;
            }

            foreach ((array) ($order['line_items'] ?? []) as $line) {
                $quantity = (int) ($line['quantity'] ?? 0);
                $itemsSold += $quantity;

                $title = (string) ($line['title'] ?? 'Product');
                $lineRevenue = (float) ($line['price'] ?? 0) * $quantity;

                if (! isset($products[$title])) {
                    $products[$title] = ['name' => $title, 'quantity' => 0, 'revenue' => 0.0];
                }
                $products[$title]['quantity'] += $quantity;
                $products[$title]['revenue'] += $lineRevenue;
            }
        }

        // Guaranteed >= 1 here: the empty-orders case returned early above.
        $orderCount = count($orders);
        $topProducts = array_values($products);
        usort($topProducts, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $topProducts = array_slice($topProducts, 0, 10);

        return $result
            ->metric('ecommerce.revenue', round($revenue, 2), $currency)
            ->metric('ecommerce.orders', $orderCount)
            ->metric('ecommerce.aov', round($revenue / $orderCount, 2), $currency)
            ->metric('ecommerce.items_sold', $itemsSold)
            ->metric('ecommerce.refunds', round($refunds, 2), $currency)
            ->snapshot([
                'active' => true,
                'currency' => $currency,
                'top_products' => $topProducts,
                'timeseries' => $this->buildTimeseries($byDay, $range),
            ]);
    }

    /**
     * Sum a Shopify order's refunded line amounts.
     *
     * @param  array<string, mixed>  $order
     */
    private function refundTotal(array $order): float
    {
        $total = 0.0;

        foreach ((array) ($order['refunds'] ?? []) as $refund) {
            foreach ((array) ($refund['transactions'] ?? []) as $transaction) {
                $total += (float) ($transaction['amount'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Every day in the range with its revenue (0 where no orders landed), so
     * the chart has consistent day spacing regardless of gaps.
     *
     * @param  array<string, float>  $byDay
     * @return array<int, array{date: string, value: float}>
     */
    private function buildTimeseries(array $byDay, DateRange $range): array
    {
        $days = [];
        $cursor = $range->start;

        while ($cursor->lessThanOrEqualTo($range->end)) {
            $day = $cursor->toDateString();
            $days[] = ['date' => $day, 'value' => round($byDay[$day] ?? 0.0, 2)];
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
