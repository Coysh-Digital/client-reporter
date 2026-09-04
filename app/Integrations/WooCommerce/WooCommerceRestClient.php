<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the WooCommerce REST API (v3). Authenticates with a
 * store's REST API consumer key/secret over HTTPS Basic auth — no companion
 * plugin required. This is the direct-store path; WooCommerce data can also
 * arrive through the WordPress connector.
 */
class WooCommerceRestClient
{
    private readonly string $baseUrl;

    public function __construct(
        string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        $this->baseUrl = $this->normaliseStore($storeUrl).'/wp-json/wc/v3';
    }

    /**
     * Period sales totals (the first — and only — row of the sales report).
     *
     * @return array<string, mixed>
     */
    public function salesReport(DateRange $range): array
    {
        $rows = $this->get('/reports/sales', [
            'date_min' => $range->start->toDateString(),
            'date_max' => $range->end->toDateString(),
        ]);

        $first = is_array($rows[0] ?? null) ? $rows[0] : [];

        return $first;
    }

    /**
     * Best-selling products for the period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topSellers(DateRange $range): array
    {
        $rows = $this->get('/reports/top_sellers', [
            'date_min' => $range->start->toDateString(),
            'date_max' => $range->end->toDateString(),
        ]);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * The store's configured currency code (e.g. "GBP"), or null if unavailable.
     */
    public function currency(): ?string
    {
        try {
            $setting = $this->get('/settings/general/woocommerce_currency');
        } catch (IntegrationException) {
            return null;
        }

        $value = $setting['value'] ?? null;

        return is_string($value) && $value !== '' ? strtoupper($value) : null;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<int|string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(20)->acceptJson()->get($this->baseUrl.$path, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach the WooCommerce store. Check the store URL and try again.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('WooCommerce rejected the API keys. Check the consumer key/secret and that they have Read access.');
        }

        if ($response->status() === 404) {
            throw new IntegrationException('WooCommerce REST API not found at this URL. Make sure WooCommerce is active and the store URL is correct.');
        }

        if ($response->failed()) {
            throw new IntegrationException('WooCommerce returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }

    private function normaliseStore(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }
}
