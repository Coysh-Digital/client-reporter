<?php

declare(strict_types=1);

namespace App\Integrations\EmailOctopus;

use App\Integrations\Support\AbstractHttpClient;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
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

    /** Never look at more than this many recent campaigns for a report. */
    private const MAX_CAMPAIGNS = 10;

    /**
     * The campaigns sent within the period, each with its performance summary,
     * most recent first. EmailOctopus has no date filter on /campaigns, so a
     * page is fetched and filtered by sent_at, then a summary is pulled per
     * campaign (bounded by MAX_CAMPAIGNS).
     *
     * @return array<int, array{name: string, sent_at: string, recipients: int, opens: int, clicks: int, open_rate: float, click_rate: float, unsubscribed: int}>
     */
    public function campaigns(DateRange $range): array
    {
        $body = $this->request('/campaigns', ['limit' => 100]);

        $sent = [];
        foreach ($body['data'] ?? [] as $campaign) {
            $sentAt = $campaign['sent_at'] ?? null;
            if (($campaign['status'] ?? '') !== 'sent' || ! is_string($sentAt) || $sentAt === '') {
                continue;
            }
            if (! $range->contains(CarbonImmutable::parse($sentAt))) {
                continue;
            }
            $sent[] = [
                'id' => (string) ($campaign['id'] ?? ''),
                'name' => (string) ($campaign['name'] ?? $campaign['subject'] ?? 'Campaign'),
                'sent_at' => $sentAt,
            ];
        }

        usort($sent, fn (array $a, array $b): int => strcmp($b['sent_at'], $a['sent_at']));

        return array_map(function (array $campaign): array {
            $summary = $this->request("/campaigns/{$campaign['id']}/reports/summary");
            $recipients = (int) ($summary['sent'] ?? 0);
            $opens = (int) ($summary['opened']['unique'] ?? 0);
            $clicks = (int) ($summary['clicked']['unique'] ?? 0);

            return [
                'name' => $campaign['name'],
                'sent_at' => $campaign['sent_at'],
                'recipients' => $recipients,
                'opens' => $opens,
                'clicks' => $clicks,
                'open_rate' => $recipients > 0 ? round($opens / $recipients * 100, 1) : 0.0,
                'click_rate' => $recipients > 0 ? round($clicks / $recipients * 100, 1) : 0.0,
                'unsubscribed' => (int) ($summary['unsubscribed'] ?? 0),
            ];
        }, array_slice($sent, 0, self::MAX_CAMPAIGNS));
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
