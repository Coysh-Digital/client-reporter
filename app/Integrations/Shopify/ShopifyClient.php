<?php

declare(strict_types=1);

namespace App\Integrations\Shopify;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Shopify Admin REST API. Uses an Admin API access
 * token (from a custom app) sent as the X-Shopify-Access-Token header.
 */
class ShopifyClient
{
    /** Never follow more than this many pages, to bound a busy store's export. */
    private const MAX_PAGES = 10;

    private readonly string $shop;

    public function __construct(
        string $shopDomain,
        private readonly string $accessToken,
        private readonly string $apiVersion = '2024-01',
    ) {
        $this->shop = $this->normaliseShop($shopDomain);
    }

    /**
     * Paid orders created within the period, following cursor pagination up to a
     * sane cap.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orders(DateRange $range): array
    {
        $orders = [];
        $url = $this->baseUrl().'/orders.json';
        $params = [
            'status' => 'any',
            'financial_status' => 'paid',
            'created_at_min' => $range->start->toIso8601String(),
            'created_at_max' => $range->end->toIso8601String(),
            'limit' => 250,
        ];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->get($url, $params);
            $rows = $response->json('orders', []);
            if (is_array($rows)) {
                $orders = array_merge($orders, $rows);
            }

            $next = $this->nextPageUrl($response);
            if ($next === null) {
                break;
            }

            // The next-page link carries its own page_info cursor; Shopify
            // forbids sending other filters alongside it.
            $url = $next;
            $params = [];
        }

        return $orders;
    }

    /**
     * The store's shop record — used for a lightweight verify and the currency.
     *
     * @return array<string, mixed>
     */
    public function shop(): array
    {
        $shop = $this->get($this->baseUrl().'/shop.json')->json('shop', []);

        return is_array($shop) ? $shop : [];
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function get(string $url, array $params = []): Response
    {
        try {
            $response = Http::withHeaders(['X-Shopify-Access-Token' => $this->accessToken])
                ->timeout(20)->acceptJson()->get($url, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Shopify. Please check the store domain and try again.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Shopify rejected the access token. Check the token and its scopes (read_orders, read_products).');
        }

        if ($response->status() === 404) {
            throw new IntegrationException('Shopify store not found. Check the store domain (e.g. your-store.myshopify.com).');
        }

        if ($response->failed()) {
            throw new IntegrationException('Shopify returned an error (HTTP '.$response->status().').');
        }

        return $response;
    }

    /**
     * Extract the rel="next" URL from Shopify's Link header, if present.
     */
    private function nextPageUrl(Response $response): ?string
    {
        $link = $response->header('Link');
        if ($link === '' || ! str_contains($link, 'rel="next"')) {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (str_contains($part, 'rel="next"') && preg_match('/<([^>]+)>/', $part, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private function baseUrl(): string
    {
        return "https://{$this->shop}/admin/api/{$this->apiVersion}";
    }

    /**
     * Accept a bare handle, a myshopify domain, or a full URL and reduce it to
     * the canonical "handle.myshopify.com" host.
     */
    private function normaliseShop(string $domain): string
    {
        $host = strtolower(trim($domain));
        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = rtrim(explode('/', $host)[0], '/');

        if ($host !== '' && ! str_contains($host, '.')) {
            $host .= '.myshopify.com';
        }

        return $host;
    }
}
