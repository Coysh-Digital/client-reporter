<?php

declare(strict_types=1);

namespace App\Integrations\Shopify;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Read-only client for the Shopify Admin REST API. Uses an Admin API access
 * token (from a custom app) sent as the X-Shopify-Access-Token header.
 */
class ShopifyClient extends AbstractHttpClient
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

    protected function provider(): string
    {
        return 'Shopify';
    }

    protected function unreachableMessage(): string
    {
        return 'Could not reach Shopify. Please check the store domain and try again.';
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
        $url = $this->apiBase().'/orders.json';
        $params = [
            'status' => 'any',
            'financial_status' => 'paid',
            'created_at_min' => $range->start->toIso8601String(),
            'created_at_max' => $range->end->toIso8601String(),
            'limit' => 250,
        ];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->request($url, $params);
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
        $shop = $this->request($this->apiBase().'/shop.json')->json('shop', []);

        return is_array($shop) ? $shop : [];
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function request(string $url, array $params = []): Response
    {
        $response = $this->get($url, $params, fn (PendingRequest $r): PendingRequest => $r->withHeaders(['X-Shopify-Access-Token' => $this->accessToken]));

        return $this->guard($response, [
            401 => 'Shopify rejected the access token. Check the token and its scopes (read_orders, read_products).',
            403 => 'Shopify rejected the access token. Check the token and its scopes (read_orders, read_products).',
            404 => 'Shopify store not found. Check the store domain (e.g. your-store.myshopify.com).',
        ]);
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

    private function apiBase(): string
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
