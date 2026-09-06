<?php

declare(strict_types=1);

namespace App\Integrations\Mailchimp;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the Mailchimp Marketing API v3. The API server lives in
 * the account's own datacenter, encoded as a suffix on the API key itself
 * (e.g. "…-us21"), so the base URL is derived from the key rather than
 * configured separately.
 */
class MailchimpClient extends AbstractHttpClient
{
    private readonly string $apiBase;

    public function __construct(private readonly string $apiKey)
    {
        $dc = Str::afterLast($apiKey, '-');

        if ($dc === '' || $dc === $apiKey) {
            throw new IntegrationException('The Mailchimp API key looks invalid — it should end with a datacenter suffix, e.g. "-us21".');
        }

        $this->apiBase = "https://{$dc}.api.mailchimp.com/3.0";
    }

    protected function provider(): string
    {
        return 'Mailchimp';
    }

    /**
     * @return array<string, mixed>
     */
    public function list(string $listId): array
    {
        return $this->request("/lists/{$listId}");
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
        $data = $this->request("/lists/{$listId}/growth-history", ['count' => 120]);

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
    private function request(string $path, array $query = []): array
    {
        $response = $this->get(
            $this->apiBase.$path,
            $query,
            fn (PendingRequest $r): PendingRequest => $r->withBasicAuth('client-reporter', $this->apiKey),
        );

        $this->guard($response, [
            401 => 'Mailchimp rejected the API key.',
            404 => 'Mailchimp audience not found — check the Audience ID.',
        ] + ($response->successful() ? [] : [$response->status() => "Mailchimp returned an unexpected response ({$response->status()})."]));

        return (array) $response->json();
    }
}
