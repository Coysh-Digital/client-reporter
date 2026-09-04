<?php

declare(strict_types=1);

namespace App\Integrations\Stripe;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects Stripe payments into the shared ecommerce.* metric layer plus a
 * 'stripe' snapshot — the same shape WooCommerce/Craft/Shopify emit, so the
 * generic Store block renders it. Stripe is a payment processor, so it reports
 * revenue, payments (as "orders"), average payment and refunds; it has no
 * product line items, so "items sold" and top products are left blank.
 */
class SalesCollector extends AbstractCollector
{
    /** Currencies Stripe charges in whole units rather than cents. */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public function key(): string
    {
        return 'stripe';
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new StripeClient((string) $connection->credential('api_key'));

        $revenue = 0.0;
        $refunds = 0.0;
        $count = 0;
        $currency = null;

        /** @var array<string, float> $byDay */
        $byDay = [];

        foreach ($client->charges($range) as $charge) {
            if (($charge['status'] ?? null) !== 'succeeded' || ($charge['paid'] ?? false) !== true) {
                continue;
            }

            $chargeCurrency = isset($charge['currency']) ? strtoupper((string) $charge['currency']) : 'USD';
            $currency ??= $chargeCurrency;
            $divisor = in_array($chargeCurrency, self::ZERO_DECIMAL, true) ? 1 : 100;

            $chargeRevenue = (float) ($charge['amount'] ?? 0) / $divisor;
            $revenue += $chargeRevenue;
            $refunds += (float) ($charge['amount_refunded'] ?? 0) / $divisor;
            $count++;

            if (isset($charge['created'])) {
                $day = CarbonImmutable::createFromTimestamp((int) $charge['created'])->toDateString();
                $byDay[$day] = ($byDay[$day] ?? 0.0) + $chargeRevenue;
            }
        }

        return CollectorResult::make()
            ->metric('ecommerce.revenue', round($revenue, 2), $currency)
            ->metric('ecommerce.orders', $count)
            ->metric('ecommerce.aov', $count > 0 ? round($revenue / $count, 2) : 0, $currency)
            ->metric('ecommerce.refunds', round($refunds, 2), $currency)
            ->snapshot([
                'active' => true,
                'currency' => $currency,
                'top_products' => [],
                'timeseries' => $this->buildTimeseries($byDay, $range),
            ]);
    }

    /**
     * Every day in the range with its revenue (0 where no charges landed), so
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
