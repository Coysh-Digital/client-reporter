<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAnalytics;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Google Analytics Data API (GA4). Exchanges a stored
 * refresh token for a short-lived access token, then runs reports. Uses the REST
 * API directly to avoid a heavy SDK dependency.
 */
class GoogleAnalyticsClient
{
    public function __construct(
        private readonly string $refreshToken,
        private readonly string $propertyId,
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
     * Run a GA4 report.
     *
     * @param  array<int, string>  $metrics
     * @param  array<int, string>  $dimensions
     * @return array<string, mixed>
     */
    public function runReport(DateRange $range, array $metrics, array $dimensions = [], int $limit = 10): array
    {
        try {
            $response = Http::withToken($this->accessToken())->timeout(30)->post(
                "https://analyticsdata.googleapis.com/v1beta/properties/{$this->propertyId}:runReport",
                [
                    'dateRanges' => [[
                        'startDate' => $range->start->toDateString(),
                        'endDate' => $range->end->toDateString(),
                    ]],
                    'metrics' => array_map(fn (string $m): array => ['name' => $m], $metrics),
                    'dimensions' => array_map(fn (string $d): array => ['name' => $d], $dimensions),
                    'limit' => $limit,
                ],
            );
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google Analytics. Please try again shortly.');
        }

        if ($response->status() === 403) {
            throw new IntegrationException('Google Analytics denied access to this property. Check the property ID and permissions.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Google Analytics returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
