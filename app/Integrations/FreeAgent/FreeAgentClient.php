<?php

declare(strict_types=1);

namespace App\Integrations\FreeAgent;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the FreeAgent API (v2). Exchanges a stored refresh
 * token for a short-lived access token, then reads contacts and their
 * invoices — used only to sync the agency's own billing into the local
 * invoice ledger, never to read a client's own accounts.
 */
class FreeAgentClient
{
    private const BASE = 'https://api.freeagent.com/v2';

    private ?string $token = null;

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        try {
            $response = Http::asForm()->timeout(20)->post(self::BASE.'/token_endpoint', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach FreeAgent. Please try again shortly.');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new IntegrationException('FreeAgent declined the connection. It may need to be reconnected.');
        }

        return $this->token = $token;
    }

    /**
     * Every contact on the account, so each can be matched to a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contacts(): array
    {
        return $this->getAllPages('/contacts', ['view' => 'active'], 'contacts');
    }

    /**
     * A contact's invoices (all statuses), newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoicesForContact(string $contactUrl): array
    {
        return $this->getAllPages('/invoices', ['contact' => $contactUrl, 'view' => 'all', 'sort' => '-dated_on'], 'invoices');
    }

    /**
     * Fetch every page of a list resource. FreeAgent paginates at 25 items by
     * default, so request the 100-item maximum and keep following pages until
     * one comes back short — that page is the last.
     *
     * @param  array<string, scalar>  $params
     * @return array<int, array<string, mixed>>
     */
    private function getAllPages(string $path, array $params, string $key): array
    {
        $perPage = 100;
        $page = 1;
        $items = [];

        do {
            $data = $this->get($path, $params + ['per_page' => $perPage, 'page' => $page]);
            $batch = is_array($data[$key] ?? null) ? $data[$key] : [];

            foreach ($batch as $item) {
                $items[] = $item;
            }

            $page++;
        } while (count($batch) === $perPage && $page <= 100);

        return $items;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        try {
            $response = Http::withToken($this->accessToken())->timeout(20)->acceptJson()
                ->get(self::BASE.$path, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach FreeAgent. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('FreeAgent rejected the request. The connection may need to be reconnected.');
        }

        if ($response->failed()) {
            throw new IntegrationException('FreeAgent returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
