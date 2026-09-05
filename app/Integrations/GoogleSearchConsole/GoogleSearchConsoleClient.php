<?php

declare(strict_types=1);

namespace App\Integrations\GoogleSearchConsole;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\GoogleOAuth;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Read-only client for the Google Search Console Search Analytics API. Exchanges
 * a stored refresh token for an access token, then queries search performance
 * (clicks, impressions, CTR, position) for a verified property.
 */
class GoogleSearchConsoleClient extends AbstractHttpClient
{
    protected int $timeout = 30;

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $siteUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    protected function provider(): string
    {
        return 'Google Search Console';
    }

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
        $token = $this->accessToken();

        $response = $this->post(
            "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query",
            [
                'startDate' => $range->start->toDateString(),
                'endDate' => $range->end->toDateString(),
                'dimensions' => $dimensions,
                'rowLimit' => $rowLimit,
            ],
            configure: fn (PendingRequest $r): PendingRequest => $r->withToken($token),
        );

        $this->guardWithReason($response, [
            403 => 'Search Console denied access to this property. Check the property URL and that the account is verified for it.',
        ], 'Search Console returned an error (HTTP :status). Check the property URL.');

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
        $token = $this->accessToken();

        $response = $this->get(
            'https://www.googleapis.com/webmasters/v3/sites',
            configure: fn (PendingRequest $r): PendingRequest => $r->withToken($token),
        );

        $this->guardWithReason($response, [
            403 => 'Search Console denied the request (HTTP 403). Make sure the '
                .'"Google Search Console API" is enabled in your Google Cloud project, '
                .'and reconnect if you granted access before Search Console was added.',
        ], 'Search Console returned an error (HTTP :status).');

        $entries = $response->json('siteEntry', []);

        return is_array($entries) ? $entries : [];
    }

    private function accessToken(): string
    {
        return GoogleOAuth::accessToken($this->refreshToken, $this->clientId, $this->clientSecret);
    }

    /**
     * Guard a response, appending the specific reason Google gave so a 403
     * says *why* (API disabled vs missing scope vs no access), not just a code.
     *
     * @param  array<int, string>  $messages
     */
    private function guardWithReason(Response $response, array $messages, string $fallback): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401) {
            GoogleOAuth::forgetAccessToken($this->refreshToken, $this->clientId);
        }

        $status = $response->status();
        $message = ($messages[$status] ?? str_replace(':status', (string) $status, $fallback)).$this->reason($response);

        $this->guard($response, [$status => $message]);
    }

    private function reason(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== '' ? ' Google said: '.$message : '';
    }
}
