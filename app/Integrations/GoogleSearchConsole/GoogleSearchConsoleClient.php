<?php

declare(strict_types=1);

namespace App\Integrations\GoogleSearchConsole;

use App\Integrations\Support\GoogleOAuth;
use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Google Search Console Search Analytics API. Exchanges
 * a stored refresh token for an access token, then queries search performance
 * (clicks, impressions, CTR, position) for a verified property.
 */
class GoogleSearchConsoleClient
{
    public function __construct(
        private readonly string $refreshToken,
        private readonly string $siteUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    /**
     * Run a Search Analytics query. With no dimensions it returns a single
     * aggregate row for the period.
     *
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    public function query(DateRange $range, array $dimensions = [], int $rowLimit = 25): array
    {
        $site = rawurlencode($this->siteUrl);
        $token = GoogleOAuth::accessToken($this->refreshToken, $this->clientId, $this->clientSecret);

        try {
            $response = Http::withToken($token)->timeout(30)->post(
                "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query",
                [
                    'startDate' => $range->start->toDateString(),
                    'endDate' => $range->end->toDateString(),
                    'dimensions' => $dimensions,
                    'rowLimit' => $rowLimit,
                ],
            );
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google Search Console. Please try again shortly.');
        }

        if ($response->status() === 403) {
            throw new IntegrationException('Search Console denied access to this property. Check the property URL and that the account is verified for it.'.$this->reason($response));
        }

        if ($response->failed()) {
            throw new IntegrationException('Search Console returned an error (HTTP '.$response->status().'). Check the property URL.'.$this->reason($response));
        }

        $rows = $response->json('rows', []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Every verified property in Search Console, for the workspace connect flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sites(): array
    {
        $token = GoogleOAuth::accessToken($this->refreshToken, $this->clientId, $this->clientSecret);

        try {
            $response = Http::withToken($token)->timeout(20)->acceptJson()
                ->get('https://www.googleapis.com/webmasters/v3/sites');
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Google Search Console. Please try again shortly.');
        }

        if ($response->status() === 403) {
            throw new IntegrationException(
                'Search Console denied the request (HTTP 403). Make sure the '
                .'"Google Search Console API" is enabled in your Google Cloud project, '
                .'and reconnect if you granted access before Search Console was added.'
                .$this->reason($response),
            );
        }

        if ($response->failed()) {
            throw new IntegrationException('Search Console returned an error (HTTP '.$response->status().').'.$this->reason($response));
        }

        $entries = $response->json('siteEntry', []);

        return is_array($entries) ? $entries : [];
    }

    /**
     * The specific reason Google gave, appended to our own message so a 403 says
     * *why* (API disabled vs missing scope vs no access) instead of just a code.
     */
    private function reason(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== '' ? ' Google said: '.$message : '';
    }
}
