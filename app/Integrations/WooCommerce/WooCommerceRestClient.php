<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the WooCommerce REST API (v3). Authenticates with a
 * store's REST API consumer key/secret over HTTPS Basic auth — no companion
 * plugin required. This is the direct-store path; WooCommerce data can also
 * arrive through the WordPress connector.
 */
class WooCommerceRestClient extends AbstractHttpClient
{
    private readonly string $storeUrl;

    public function __construct(
        string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        $this->storeUrl = $this->normaliseStore($storeUrl);
    }

    protected function provider(): string
    {
        return 'WooCommerce';
    }

    protected function baseUrl(): ?string
    {
        return $this->storeUrl;
    }

    protected function unreachableMessage(): string
    {
        return 'Could not reach the WooCommerce store. Check the store URL and try again.';
    }

    /**
     * Period sales totals (the first — and only — row of the sales report).
     *
     * @return array<string, mixed>
     */
    public function salesReport(DateRange $range): array
    {
        $rows = $this->request('/reports/sales', [
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
        $rows = $this->request('/reports/top_sellers', [
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
            $setting = $this->request('/settings/general/woocommerce_currency');
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
    private function request(string $path, array $params = []): array
    {
        $response = $this->get(
            '/wp-json/wc/v3'.$path,
            $params,
            fn (PendingRequest $r): PendingRequest => $r->withBasicAuth($this->consumerKey, $this->consumerSecret),
        );

        $this->guard($response, [
            401 => 'WooCommerce rejected the API keys. Check the consumer key/secret and that they have Read access.',
            403 => 'WooCommerce rejected the API keys. Check the consumer key/secret and that they have Read access.',
            404 => 'WooCommerce REST API not found at this URL. Make sure WooCommerce is active and the store URL is correct.',
        ]);

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
