<?php

declare(strict_types=1);

namespace App\Integrations\Connector;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\PendingRequest;

/**
 * Makes read-only, HMAC-signed GET requests to a companion connector (the
 * WordPress or Craft plugin). Client Reporter always PULLS; the plugin only ever
 * responds with data. Every request is signed over method + path + timestamp +
 * body hash so the plugin can verify authenticity, reject stale timestamps
 * (replay/timestamp validation) and reject replayed nonces.
 */
class SignedConnectorClient extends AbstractHttpClient
{
    /** Each request carries a single-use nonce, so a retry would be rejected as a replay. */
    protected int $retries = 0;

    public function __construct(
        private readonly string $siteUrl,
        private readonly string $secret,
        private readonly string $pathPrefix = '/wp-json/client-reporter/v1/',
    ) {}

    protected function provider(): string
    {
        return 'the website';
    }

    protected function baseUrl(): ?string
    {
        return $this->siteUrl;
    }

    protected function unreachableMessage(): string
    {
        return 'Could not reach the website. Check the URL and that the plugin is active.';
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function fetch(string $endpoint, array $query = []): array
    {
        $path = '/'.trim($this->pathPrefix, '/').'/'.ltrim($endpoint, '/');
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        $signature = $this->sign('GET', $path, $timestamp, $nonce, '');

        $response = $this->get($path, $query, fn (PendingRequest $r): PendingRequest => $r->withHeaders([
            'X-CR-Timestamp' => $timestamp,
            'X-CR-Nonce' => $nonce,
            'X-CR-Signature' => $signature,
        ]));

        $this->guard($response, [
            401 => 'The website rejected the connection. The connection code may be wrong or was rotated.',
            403 => 'The website rejected the connection. The connection code may be wrong or was rotated.',
        ]);

        $data = $response->json();

        if (! is_array($data)) {
            throw new IntegrationException('The plugin returned an unexpected response.');
        }

        return $data;
    }

    /**
     * Compute the request signature. Kept identical on both ends (see the
     * companion plugin) so signatures verify.
     */
    public function sign(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        $payload = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $payload, $this->secret);
    }
}
