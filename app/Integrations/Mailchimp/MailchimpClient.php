<?php

declare(strict_types=1);

namespace App\Integrations\Mailchimp;

use App\Integrations\Support\AbstractHttpClient;
use App\Integrations\Support\IntegrationException;
use App\Support\DateRange;
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
     * Sent campaigns whose send time falls within the range, newest first, each
     * with its inline report summary. Mailchimp returns opens/clicks and rates
     * on the campaign itself, so this needs no per-campaign follow-up call.
     *
     * @return array<int, array{name: string, sent_at: string, recipients: int, opens: int, clicks: int, open_rate: float, click_rate: float, unsubscribed: ?int}>
     */
    public function campaigns(DateRange $range): array
    {
        $data = $this->request('/campaigns', [
            'status' => 'sent',
            'since_send_time' => $range->start->toIso8601String(),
            'before_send_time' => $range->end->toIso8601String(),
            'sort_field' => 'send_time',
            'sort_dir' => 'DESC',
            'count' => 20,
        ]);

        return array_map(function (array $campaign): array {
            $recipients = (int) ($campaign['emails_sent'] ?? 0);
            $summary = $campaign['report_summary'] ?? [];
            $opens = (int) ($summary['unique_opens'] ?? 0);
            $clicks = (int) ($summary['subscriber_clicks'] ?? 0);

            return [
                'name' => (string) ($campaign['settings']['title'] ?? $campaign['settings']['subject_line'] ?? 'Campaign'),
                'sent_at' => (string) ($campaign['send_time'] ?? ''),
                'recipients' => $recipients,
                'opens' => $opens,
                'clicks' => $clicks,
                'open_rate' => isset($summary['open_rate']) ? round((float) $summary['open_rate'] * 100, 1) : self::rate($opens, $recipients),
                'click_rate' => isset($summary['click_rate']) ? round((float) $summary['click_rate'] * 100, 1) : self::rate($clicks, $recipients),
                'unsubscribed' => null,
            ];
        }, $data['campaigns'] ?? []);
    }

    private static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
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
