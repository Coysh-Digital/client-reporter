<?php

declare(strict_types=1);

namespace App\Integrations\Xero;

use App\Integrations\Support\IntegrationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Xero Accounting API. A Xero login can authorise
 * several organisations ("tenants"); this reads the first one connected
 * (see {@see firstTenantId()}) — multi-org selection is a documented v1
 * simplification. The Accounting API's JSON still uses the legacy .NET date
 * format (e.g. "/Date(1722816000000+0000)/"), so {@see parseDate()} unpacks
 * that rather than trusting it's ISO8601.
 */
class XeroClient
{
    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    private const API_BASE = 'https://api.xero.com/api.xro/2.0';

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function accessToken(): string
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(20)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                ]);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Xero. Please try again shortly.');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new IntegrationException('Xero declined the connection. It may need to be reconnected.');
        }

        return $token;
    }

    /**
     * The first organisation ("tenant") this connection has been authorised
     * for. A Xero login can authorise several; picking the first is a
     * deliberate v1 simplification.
     */
    public function firstTenantId(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)->timeout(20)->acceptJson()
                ->get('https://api.xero.com/connections');
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Xero. Please try again shortly.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Xero returned an error (HTTP '.$response->status().') while listing organisations.');
        }

        $connections = $response->json();

        return is_array($connections) && isset($connections[0]['tenantId']) ? (string) $connections[0]['tenantId'] : null;
    }

    /**
     * Every contact for the organisation, so each can be matched to a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contacts(string $accessToken, string $tenantId): array
    {
        $data = $this->get($accessToken, $tenantId, '/Contacts');

        return is_array($data['Contacts'] ?? null) ? $data['Contacts'] : [];
    }

    /**
     * A contact's sales invoices (ACCREC — money owed to the agency, never
     * bills the agency owes).
     *
     * @return array<int, array<string, mixed>>
     */
    public function invoicesForContact(string $accessToken, string $tenantId, string $contactId): array
    {
        $data = $this->get($accessToken, $tenantId, '/Invoices', [
            'ContactIDs' => $contactId,
            'where' => 'Type=="ACCREC"',
        ]);

        return is_array($data['Invoices'] ?? null) ? $data['Invoices'] : [];
    }

    /**
     * Unpack Xero's legacy .NET JSON date format, e.g.
     * "/Date(1722816000000+0000)/" — a plain millisecond epoch is never valid
     * here, so this must be parsed rather than passed to Carbon directly.
     */
    public static function parseDate(?string $raw): ?CarbonImmutable
    {
        if ($raw === null || preg_match('/\/Date\((\d+)/', $raw, $matches) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromTimestampMs((int) $matches[1]);
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function get(string $accessToken, string $tenantId, string $path, array $params = []): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Xero-tenant-id' => $tenantId])
                ->timeout(20)->acceptJson()
                ->get(self::API_BASE.$path, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Xero. Please try again shortly.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new IntegrationException('Xero rejected the request. The connection may need to be reconnected.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Xero returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
