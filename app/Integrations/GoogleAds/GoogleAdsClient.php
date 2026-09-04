<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAds;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Google Ads API (REST + GAQL). Exchanges a stored
 * refresh token for a short-lived access token, then runs a single
 * account-level query for the period's totals.
 */
class GoogleAdsClient
{
    private const API_VERSION = 'v17';

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $customerId,
        private readonly string $developerToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function accessToken(): string
    {
        try {
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google. Please try again shortly.');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new IntegrationException('Google declined the connection. It may need to be reconnected.');
        }

        return $token;
    }

    /**
     * Account-level spend, clicks, impressions and conversions for the period.
     * `segments.date` returns one row per day, summed here into period totals.
     *
     * @return array{spend: float, clicks: int, impressions: int, conversions: float, currency: ?string}
     */
    public function summary(DateRange $range): array
    {
        $query = 'SELECT customer.currency_code, metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.conversions '
            ."FROM customer WHERE segments.date BETWEEN '{$range->start->toDateString()}' AND '{$range->end->toDateString()}'";

        $customerId = str_replace('-', '', $this->customerId);

        try {
            $response = Http::withToken($this->accessToken())
                ->withHeaders(['developer-token' => $this->developerToken])
                ->timeout(30)
                ->post('https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$customerId}/googleAds:search", [
                    'query' => $query,
                ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google Ads. Please try again shortly.');
        }

        if ($response->status() === 403 || $response->status() === 401) {
            throw new IntegrationException('Google Ads denied access to this account. Check the customer ID, developer token and permissions.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Google Ads returned an error (HTTP '.$response->status().').');
        }

        $costMicros = 0;
        $clicks = 0;
        $impressions = 0;
        $conversions = 0.0;
        $currency = null;

        foreach ((array) $response->json('results', []) as $row) {
            $metrics = $row['metrics'] ?? [];
            $costMicros += (int) ($metrics['costMicros'] ?? 0);
            $clicks += (int) ($metrics['clicks'] ?? 0);
            $impressions += (int) ($metrics['impressions'] ?? 0);
            $conversions += (float) ($metrics['conversions'] ?? 0);
            $currency ??= $row['customer']['currencyCode'] ?? null;
        }

        return [
            'spend' => $costMicros / 1_000_000,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'conversions' => $conversions,
            'currency' => $currency,
        ];
    }
}
