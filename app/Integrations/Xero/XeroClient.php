<?php

declare(strict_types=1);

namespace App\Integrations\Xero;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\AuthenticationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * Read-only client for the Xero Accounting API. A Xero login can authorise
 * several organisations ("tenants"); this reads the first one connected
 * (see {@see firstTenantId()}) — multi-org selection is a documented v1
 * simplification. The Accounting API's JSON still uses the legacy .NET date
 * format (e.g. "/Date(1722816000000+0000)/"), so {@see parseDate()} unpacks
 * that rather than trusting it's ISO8601.
 */
class XeroClient extends AbstractHttpClient
{
    private const TOKEN_URL = 'https://identity.xero.com/connect/token';

    private const API_BASE = 'https://api.xero.com/api.xro/2.0';

    public function __construct(
        private readonly string $refreshToken,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    protected function provider(): string
    {
        return 'Xero';
    }

    public function accessToken(): string
    {
        $response = $this->post(
            self::TOKEN_URL,
            ['grant_type' => 'refresh_token', 'refresh_token' => $this->refreshToken],
            asForm: true,
            configure: fn (PendingRequest $r): PendingRequest => $r->withBasicAuth($this->clientId, $this->clientSecret),
        );

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token)) {
            throw new AuthenticationException('Xero declined the connection. It may need to be reconnected.');
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
        $response = $this->get('https://api.xero.com/connections', configure: fn (PendingRequest $r): PendingRequest => $r->withToken($accessToken));

        $this->guard($response, $response->successful() ? [] : [
            $response->status() => 'Xero returned an error (HTTP '.$response->status().') while listing organisations.',
        ]);

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
        $data = $this->request($accessToken, $tenantId, '/Contacts');

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
        $data = $this->request($accessToken, $tenantId, '/Invoices', [
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
    private function request(string $accessToken, string $tenantId, string $path, array $params = []): array
    {
        $response = $this->get(
            self::API_BASE.$path,
            $params,
            fn (PendingRequest $r): PendingRequest => $r->withToken($accessToken)->withHeaders(['Xero-tenant-id' => $tenantId]),
        );

        $this->guard($response, [
            401 => 'Xero rejected the request. The connection may need to be reconnected.',
            403 => 'Xero rejected the request. The connection may need to be reconnected.',
        ]);

        return (array) $response->json();
    }
}
