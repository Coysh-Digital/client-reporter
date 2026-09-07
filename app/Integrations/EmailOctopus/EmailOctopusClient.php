<?php

declare(strict_types=1);

namespace App\Integrations\EmailOctopus;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Illuminate\Http\Client\PendingRequest;

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
        $query = [
            'status' => 'subscribed',
            'created_at.gte' => $range->start->toIso8601String(),
            'created_at.lte' => $range->end->toIso8601String(),
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

        $this->guard($response, [
            401 => 'EmailOctopus rejected the API key.',
            403 => 'EmailOctopus rejected the API key.',
            404 => 'EmailOctopus list not found — check the List ID.',
        ]);

        return (array) $response->json();
    }
}
