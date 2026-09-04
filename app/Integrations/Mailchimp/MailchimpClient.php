<?php

declare(strict_types=1);

namespace App\Integrations\Mailchimp;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the Mailchimp Marketing API v3. The API server lives in
 * the account's own datacenter, encoded as a suffix on the API key itself
 * (e.g. "…-us21"), so the base URL is derived from the key rather than
 * configured separately.
 */
class MailchimpClient
{
    private readonly string $baseUrl;

    public function __construct(private readonly string $apiKey)
    {
        $dc = Str::afterLast($apiKey, '-');

        if ($dc === '' || $dc === $apiKey) {
            throw new IntegrationException('The Mailchimp API key looks invalid — it should end with a datacenter suffix, e.g. "-us21".');
        }

        $this->baseUrl = "https://{$dc}.api.mailchimp.com/3.0";
    }

    /**
     * @return array<string, mixed>
     */
    public function list(string $listId): array
    {
        return $this->get("/lists/{$listId}");
    }

    /**
     * Per-calendar-month growth entries for the audience: existing, imports
     * and optins counts. There is no arbitrary date-range query — only whole
     * months — so callers should only count months fully contained in their
     * requested period.
     *
     * @return array<int, array{month: string, existing: int, imports: int, optins: int}>
     */
    public function growthHistory(string $listId): array
    {
        $data = $this->get("/lists/{$listId}/growth-history", ['count' => 120]);

        return array_map(fn (array $row): array => [
            'month' => (string) ($row['month'] ?? ''),
            'existing' => (int) ($row['existing'] ?? 0),
            'imports' => (int) ($row['imports'] ?? 0),
            'optins' => (int) ($row['optins'] ?? 0),
        ], $data['history'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = Http::withBasicAuth('client-reporter', $this->apiKey)
                ->timeout(20)
                ->get($this->baseUrl.$path, $query);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Mailchimp. Please try again shortly.');
        }

        if ($response->status() === 401) {
            throw new IntegrationException('Mailchimp rejected the API key.');
        }

        if ($response->status() === 404) {
            throw new IntegrationException('Mailchimp audience not found — check the Audience ID.');
        }

        if (! $response->successful()) {
            throw new IntegrationException("Mailchimp returned an unexpected response ({$response->status()}).");
        }

        return (array) $response->json();
    }
}
