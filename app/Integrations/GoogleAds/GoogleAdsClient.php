<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAds;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\GoogleOAuth;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the Google Ads API (REST + GAQL). Exchanges a stored
 * refresh token for a short-lived access token (cached by GoogleOAuth), then
 * runs a single account-level query for the period's totals.
 */
class GoogleAdsClient extends AbstractHttpClient
{
    private const API_VERSION = 'v17';

    protected int $timeout = 30;

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $customerId,
        private readonly string $developerToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    protected function provider(): string
    {
        return 'Google Ads';
    }

    public function accessToken(): string
    {
        return GoogleOAuth::accessToken($this->refreshToken, $this->clientId, $this->clientSecret);
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
        $token = $this->accessToken();

        $response = $this->post(
            'https://googleads.googleapis.com/'.self::API_VERSION."/customers/{$customerId}/googleAds:search",
            ['query' => $query],
            configure: fn (PendingRequest $r): PendingRequest => $r->withToken($token)->withHeaders(['developer-token' => $this->developerToken]),
        );

        if ($response->status() === 401) {
            GoogleOAuth::forgetAccessToken($this->refreshToken, $this->clientId);
        }

        $this->guard($response, [
            401 => 'Google Ads denied access to this account. Check the customer ID, developer token and permissions.',
            403 => 'Google Ads denied access to this account. Check the customer ID, developer token and permissions.',
        ]);

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
