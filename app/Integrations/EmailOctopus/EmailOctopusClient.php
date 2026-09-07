<?php

declare(strict_types=1);

namespace App\Integrations\EmailOctopus;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Thin wrapper around the EmailOctopus v2 API. A single fixed host serves every
 * account (unlike Mailchimp's per-datacenter hosts), and the API key is sent as
 * a bearer token.
 */
class EmailOctopusClient extends AbstractHttpClient
{
    private const BASE = 'https://api.emailoctopus.com';

    /** A safety bound on how many contact pages one collection will read. */
    private const MAX_PAGES = 100;

    public function __construct(private readonly string $apiKey) {}

    protected function provider(): string
    {
        return 'EmailOctopus';
    }

    /**
     * @return array<string, mixed>
     */
    public function list(string $listId): array
    {
        return $this->request("/lists/{$listId}");
    }

    /**
     * The number of contacts that subscribed to a list within the period,
     * counted precisely by paging the contacts endpoint filtered on
     * created_at — so any date range works, not just whole months.
     */
    public function newSubscribers(string $listId, DateRange $range): int
    {
        // EmailOctopus wants the created_at filters as UTC "Zulu" timestamps
        // (e.g. 2024-01-19T12:14:28Z); a "+00:00" offset is rejected with a 400.
        $query = [
            'status' => 'subscribed',
            'created_at.gte' => $range->start->utc()->toIso8601ZuluString(),
            'created_at.lte' => $range->end->utc()->toIso8601ZuluString(),
            'limit' => 100,
        ];

        $count = 0;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = $this->request("/lists/{$listId}/contacts", $query);
            $count += count($body['data'] ?? []);

            $cursor = $body['paging']['next']['starting_after'] ?? null;
            if (! is_string($cursor) || $cursor === '') {
                break;
            }
            $query['starting_after'] = $cursor;
        }

        return $count;
    }

    /**
     * Read a list's subscriber counts, tolerating either the object or the
     * single-element-array shape the API has been seen to return for `counts`.
     *
     * @param  array<string, mixed>  $list
     * @return array{subscribed: int, unsubscribed: int, pending: int}
     */
    public static function countsFor(array $list): array
    {
        $counts = $list['counts'] ?? [];
        if (is_array($counts) && array_is_list($counts)) {
            $counts = $counts[0] ?? [];
        }

        return [
            'subscribed' => (int) ($counts['subscribed'] ?? 0),
            'unsubscribed' => (int) ($counts['unsubscribed'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query = []): array
    {
        $response = $this->get(
            self::BASE.$path,
            $query,
            fn (PendingRequest $r): PendingRequest => $r->withToken($this->apiKey),
        );

        if ($response->failed()) {
            $detail = $this->errorDetail($response);

            $this->guard($response, [
                400 => 'EmailOctopus rejected the request'.($detail !== null ? ': '.$detail : '.'),
                401 => 'EmailOctopus rejected the API key.',
                403 => 'EmailOctopus rejected the API key.',
                404 => 'EmailOctopus list not found — check the List ID.',
                422 => 'EmailOctopus rejected the request'.($detail !== null ? ': '.$detail : '.'),
            ]);
        }

        return (array) $response->json();
    }

    /**
     * EmailOctopus returns JSON errors in an RFC 7807 shape; pull the most
     * human part out so the reason for a rejection is visible, not just "HTTP 400".
     */
    private function errorDetail(Response $response): ?string
    {
        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        foreach (['detail', 'title', 'message'] as $key) {
            if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        return null;
    }
}
