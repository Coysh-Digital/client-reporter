<?php

declare(strict_types=1);

namespace App\Integrations\Matomo;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Matomo Reporting API (works with Matomo Cloud or a
 * self-hosted instance). token_auth is sent in the POST body so it never lands
 * in a URL or server log.
 */
class MatomoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $idSite,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function visitsSummary(DateRange $range): array
    {
        return $this->request('VisitsSummary.get', $range);
    }

    /**
     * @return array<string, mixed>
     */
    public function actions(DateRange $range): array
    {
        return $this->request('Actions.get', $range);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pageUrls(DateRange $range, int $limit = 5): array
    {
        $data = $this->request('Actions.getPageUrls', $range, ['flat' => 1, 'filter_limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function referrerTypes(DateRange $range, int $limit = 6): array
    {
        $data = $this->request('Referrers.getReferrerType', $range, ['filter_limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function countries(DateRange $range, int $limit = 8): array
    {
        $data = $this->request('UserCountry.getCountry', $range, ['filter_limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deviceTypes(DateRange $range, int $limit = 5): array
    {
        $data = $this->request('DevicesDetection.getType', $range, ['filter_limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function events(DateRange $range, int $limit = 10): array
    {
        $data = $this->request('Events.getAction', $range, ['filter_limit' => $limit]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * Daily unique-visitor series, keyed by date.
     *
     * @return array<string, array<string, mixed>>
     */
    public function visitorsSeries(DateRange $range): array
    {
        return $this->request('VisitsSummary.get', $range, [], 'day');
    }

    /**
     * Every site on this Matomo instance, for the workspace connect flow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allSites(): array
    {
        $params = [
            'module' => 'API',
            'method' => 'SitesManager.getAllSites',
            'format' => 'JSON',
        ];

        try {
            $response = Http::asForm()->timeout(20)
                ->post(rtrim($this->baseUrl, '/').'/index.php?'.http_build_query($params), ['token_auth' => $this->token]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Matomo. Please try again shortly.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Matomo returned an error (HTTP '.$response->status().'). Check the URL.');
        }

        $json = $response->json();

        if (is_array($json) && ($json['result'] ?? null) === 'error') {
            throw new IntegrationException('Matomo rejected the request: '.(string) ($json['message'] ?? 'check the auth token.'));
        }

        return is_array($json) ? array_values(array_filter($json, 'is_array')) : [];
    }

    /**
     * @param  array<string, scalar>  $extra
     * @return array<mixed>
     */
    private function request(string $method, DateRange $range, array $extra = [], string $period = 'range'): array
    {
        $params = array_merge([
            'module' => 'API',
            'method' => $method,
            'idSite' => $this->idSite,
            'period' => $period,
            'date' => $range->start->toDateString().','.$range->end->toDateString(),
            'format' => 'JSON',
        ], $extra);

        try {
            $response = Http::asForm()->timeout(20)
                ->post(rtrim($this->baseUrl, '/').'/index.php?'.http_build_query($params), ['token_auth' => $this->token]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Matomo. Please try again shortly.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Matomo returned an error (HTTP '.$response->status().'). Check the URL and site ID.');
        }

        $json = (array) $response->json();

        if (($json['result'] ?? null) === 'error') {
            throw new IntegrationException('Matomo rejected the request: '.(string) ($json['message'] ?? 'check the auth token.'));
        }

        return $json;
    }
}
