<?php

declare(strict_types=1);

namespace App\Integrations\GoogleAnalytics;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\GoogleOAuth;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the Google Analytics Data API (GA4). Exchanges a stored
 * refresh token for a short-lived access token (cached by GoogleOAuth), then
 * runs reports. Uses the REST API directly to avoid a heavy SDK dependency.
 */
class GoogleAnalyticsClient extends AbstractHttpClient
{
    protected int $timeout = 30;

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $propertyId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    protected function provider(): string
    {
        return 'Google Analytics';
    }

    public function accessToken(): string
    {
        return GoogleOAuth::accessToken($this->refreshToken, $this->clientId, $this->clientSecret);
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
        $token = $this->accessToken();

        $response = $this->post(
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
            configure: fn (PendingRequest $r): PendingRequest => $r->withToken($token),
        );

        if ($response->status() === 401) {
            GoogleOAuth::forgetAccessToken($this->refreshToken, $this->clientId);
        }

        $this->guard($response, [
            403 => 'Google Analytics denied access to this property. Check the property ID and permissions.',
        ]);

        return (array) $response->json();
    }
}
