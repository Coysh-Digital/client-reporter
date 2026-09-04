<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Models\Site;
use App\Reporting\Contracts\BlockType;
use App\Reporting\MetricReader;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\Format;

/**
 * A provider-agnostic store block. Reads ecommerce metrics from whichever
 * integration on the site carries them (WooCommerce under WordPress, Craft
 * Commerce under Craft, or a standalone Shopify store), so one block covers
 * every store.
 */
class EcommerceBlock extends BlockType
{
    /** key => [metric_key, label, fmt, goodUp] */
    private const METRICS = [
        'revenue' => ['ecommerce.revenue', 'Revenue', 'money', true],
        'orders' => ['ecommerce.orders', 'Orders', 'number', true],
        'aov' => ['ecommerce.aov', 'Avg order', 'money', true],
        'items_sold' => ['ecommerce.items_sold', 'Items sold', 'number', true],
    ];

    public function type(): string
    {
        // Keeps the historical type key so existing reports/templates still work.
        return 'ecommerce.summary';
    }

    public function label(): string
    {
        return 'Store performance';
    }

    public function description(): string
    {
        return 'Revenue, orders, average order value and top products — for any store (WooCommerce, Craft Commerce, Shopify or Stripe).';
    }

    public function group(): string
    {
        return 'Ecommerce';
    }

    public function availableForSite(Site $site): ?bool
    {
        // Available whenever the site has a store source (a CMS carrying store
        // data, or a standalone Shopify connection). resolve() reports
        // gracefully when that source has no sales yet.
        return app(MetricReader::class)->ecommerceSource($site) !== null;
    }

    public function neededIntegrationKeys(Site $site): array
    {
        $source = app(MetricReader::class)->ecommerceSource($site);

        return $source === null ? [] : [$source['integration_key']];
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
            BlockOption::multiselect('metrics', 'Metrics to show', [
                'revenue' => 'Revenue',
                'orders' => 'Orders',
                'aov' => 'Avg order',
                'items_sold' => 'Items sold',
            ], ['revenue', 'orders', 'aov', 'items_sold']),
            BlockOption::toggle('show_products', 'Show top products', true),
            BlockOption::number('products_limit', 'Top products to show', 4, 1, 15),
            BlockOption::toggle('show_chart', 'Show daily revenue chart', true, 'Only available for providers that report per-day sales (Shopify, Stripe).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $source = $context->reader->ecommerceSource($context->site);
        if ($source === null) {
            return ['active' => false];
        }

        $reader = $context->reader;
        $key = $source['integration_key'];
        $compare = (bool) $context->block->configValue('compare', true);
        $selected = (array) $context->block->configValue('metrics', array_keys(self::METRICS));

        $current = $reader->metrics($context->site, $key, $context->range);
        $previous = $compare && $context->comparison ? $reader->metrics($context->site, $key, $context->comparison) : [];
        $snapshot = $reader->snapshot($context->site, $key, $source['collector_key'], $context->range) ?? [];

        $currency = $snapshot['currency'] ?? ($current['ecommerce.revenue']['unit'] ?? null);

        $metrics = [];
        foreach ($selected as $metricKey) {
            if (! isset(self::METRICS[$metricKey])) {
                continue;
            }
            [$key2, $label, $fmt, $goodUp] = self::METRICS[$metricKey];
            $metrics[] = [
                'label' => $label,
                'fmt' => $fmt,
                'goodUp' => $goodUp,
                'current' => $current[$key2]['value'] ?? null,
                'previous' => $previous[$key2]['value'] ?? null,
            ];
        }

        $products = [];
        if ((bool) $context->block->configValue('show_products', true)) {
            $limit = (int) $context->block->configValue('products_limit', 4);
            $products = array_slice($snapshot['top_products'] ?? [], 0, $limit);
        }

        return [
            'active' => ($snapshot['active'] ?? false) === true || $current !== [],
            'provider' => $source['provider'],
            'currency' => $currency,
            'metrics' => $metrics,
            'top_products' => $products,
            'timeseries' => (bool) $context->block->configValue('show_chart', true) ? ($snapshot['timeseries'] ?? []) : [],
            'insight' => $this->insight($current, $previous, $currency),
        ];
    }

    /**
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $current
     * @param  array<string, array{value: float, unit: ?string, meta: array<string, mixed>}>  $previous
     */
    private function insight(array $current, array $previous, ?string $currency): ?string
    {
        $orders = $current['ecommerce.orders']['value'] ?? null;
        $revenue = $current['ecommerce.revenue']['value'] ?? null;

        if ($orders === null || $revenue === null) {
            return null;
        }

        $sentence = 'The store processed '.Format::number($orders).' '.($orders === 1.0 ? 'order' : 'orders')
            .' totalling '.Format::money($revenue, $currency).' in net revenue';

        $change = Format::change($revenue, $previous['ecommerce.revenue']['value'] ?? null);
        if ($change['percent'] === null) {
            return $sentence.' this period.';
        }

        if ($change['direction'] === 'flat') {
            return $sentence.', unchanged from the prior period.';
        }

        return $sentence.', '.$change['direction'].' '.Format::number(abs($change['percent']), 1).'% from the prior period.';
    }

    public function icon(): string
    {
        return 'cart';
    }
}
