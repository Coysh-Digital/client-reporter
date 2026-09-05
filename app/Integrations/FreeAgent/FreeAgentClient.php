<?php

declare(strict_types=1);

namespace App\Integrations\FreeAgent;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\AuthenticationException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the FreeAgent API (v2). Exchanges a stored refresh
 * token for a short-lived access token, then reads contacts and their
 * invoices — used only to sync the agency's own billing into the local
 * invoice ledger, never to read a client's own accounts.
 */
class FreeAgentClient extends AbstractHttpClient
{
    private const BASE = 'https://api.freeagent.com/v2';

    private ?string $token = null;

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    protected function provider(): string
    {
        return 'FreeAgent';
    }

    public function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $response = $this->post(self::BASE.'/token_endpoint', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ], asForm: true);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new AuthenticationException('FreeAgent declined the connection. It may need to be reconnected.');
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
     * A contact's recurring invoice schedules (all statuses).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recurringInvoicesForContact(string $contactUrl): array
    {
        return $this->getAllPages('/recurring_invoices', ['contact' => $contactUrl], 'recurring_invoices');
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
            $data = $this->request($path, $params + ['per_page' => $perPage, 'page' => $page]);
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
    private function request(string $path, array $params = []): array
    {
        $token = $this->accessToken();

        $response = $this->get(self::BASE.$path, $params, fn (PendingRequest $r): PendingRequest => $r->withToken($token));

        $this->guard($response, [
            401 => 'FreeAgent rejected the request. The connection may need to be reconnected.',
            403 => 'FreeAgent rejected the request. The connection may need to be reconnected.',
        ]);

        return (array) $response->json();
    }
}
