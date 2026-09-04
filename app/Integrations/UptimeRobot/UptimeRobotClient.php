<?php

declare(strict_types=1);

namespace App\Integrations\UptimeRobot;

use App\Integrations\Support\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin, read-only client for the UptimeRobot v2 API. Only ever reads monitor
 * data; it never creates, edits or deletes anything.
 */
class UptimeRobotClient
{
    private const BASE = 'https://api.uptimerobot.com/v2/';

    public function __construct(private readonly string $apiKey) {}

    /**
     * Fetch monitors with optional logs, response times and a custom uptime
     * range ("{startTs}_{endTs}").
     *
     * @param  array<int, int|string>  $monitorIds
     * @return array<int, array<string, mixed>>
     */
    public function monitors(array $monitorIds = [], ?string $customUptimeRange = null, bool $withLogs = false): array
    {
        $params = [
            'response_times' => 1,
            'response_times_average' => 1,
        ];

        if ($monitorIds !== []) {
            $params['monitors'] = implode('-', $monitorIds);
        }

        if ($customUptimeRange !== null) {
            $params['custom_uptime_ranges'] = $customUptimeRange;
        }

        if ($withLogs) {
            $params['logs'] = 1;
        }

        $data = $this->post('getMonitors', $params);

        return $data['monitors'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $params = []): array
    {
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post(self::BASE.$endpoint, array_merge([
                    'api_key' => $this->apiKey,
                    'format' => 'json',
                ], $params));
        } catch (ConnectionException) {
            throw new IntegrationException('Could not reach UptimeRobot. Please try again shortly.');
        }

        if ($response->failed()) {
            throw new IntegrationException('UptimeRobot returned an error (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (! is_array($data) || ($data['stat'] ?? null) !== 'ok') {
            $message = is_array($data) ? (string) data_get($data, 'error.message', 'request rejected') : 'invalid response';
            throw new IntegrationException('UptimeRobot rejected the request: '.$message);
        }

        return $data;
    }
}
