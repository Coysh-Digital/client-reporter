<?php

declare(strict_types=1);

namespace App\Integrations\Stripe;

use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Stripe API. Authenticates with a secret or
 * restricted API key (Bearer) and reads succeeded charges for a period.
 */
class StripeClient
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    /** Never follow more than this many pages, to bound a busy account. */
    private const MAX_PAGES = 20;

    public function __construct(private readonly string $apiKey) {}

    /**
     * All charges created within the period, following Stripe's cursor
     * pagination up to a sane cap.
     *
     * @return array<int, array<string, mixed>>
     */
    public function charges(DateRange $range): array
    {
        $charges = [];
        $params = [
            'created[gte]' => $range->start->getTimestamp(),
            'created[lte]' => $range->end->getTimestamp(),
            'limit' => 100,
        ];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = $this->get('/charges', $params);
            $rows = is_array($body['data'] ?? null) ? $body['data'] : [];
            $charges = array_merge($charges, $rows);

            if (($body['has_more'] ?? false) !== true || $rows === []) {
                break;
            }

            $last = end($rows);
            $params['starting_after'] = (string) ($last['id'] ?? '');
            if ($params['starting_after'] === '') {
                break;
            }
        }

        return $charges;
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        try {
            $response = Http::withToken($this->apiKey)->timeout(20)->acceptJson()
                ->get(self::BASE_URL.$path, $params);
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach Stripe. Please try again shortly.');
        }

        if ($response->status() === 401) {
            throw new IntegrationException('Stripe rejected the API key. Check the key and that it can read charges.');
        }

        if ($response->status() === 403) {
            throw new IntegrationException('This Stripe key is not permitted to read charges. Grant it read access to Charges.');
        }

        if ($response->failed()) {
            throw new IntegrationException('Stripe returned an error (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }
}
