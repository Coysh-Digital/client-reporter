<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks GitHub for a newer stable release and surfaces it to administrators.
 * Client Reporter never updates itself — it only reports that an update exists
 * and links to the release and upgrade instructions.
 */
class UpdateChecker
{
    public function __construct(private readonly Settings $settings) {}

    public function currentVersion(): string
    {
        return ltrim((string) config('client-reporter.version', '0.0.0'), 'v');
    }

    /**
     * Whether update checking is enabled — a Settings override wins over the
     * config/env default.
     */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('updates_enabled', config('client-reporter.updates.enabled', true));
    }

    /**
     * Fetch and cache the latest release. Returns the resulting status.
     *
     * @return array<string, mixed>
     */
    public function check(): array
    {
        if (! $this->enabled()) {
            return $this->status();
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'ClientReporter'])
                ->get((string) config('client-reporter.updates.endpoint'));

            if ($response->successful()) {
                $this->settings->set('update_check', [
                    'latest' => ltrim((string) $response->json('tag_name'), 'v'),
                    'url' => (string) $response->json('html_url'),
                    'checked_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable) {
            // Never let an update check break the app; try again next time.
        }

        return $this->status();
    }

    /**
     * The current update status from the cached check.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        /** @var array<string, mixed> $cache */
        $cache = (array) $this->settings->get('update_check', []);
        $latest = isset($cache['latest']) ? (string) $cache['latest'] : null;
        $current = $this->currentVersion();

        return [
            'current' => $current,
            'latest' => $latest,
            'update_available' => $this->enabled() && $latest !== null && version_compare($latest, $current, '>'),
            'url' => $cache['url'] ?? null,
            'checked_at' => $cache['checked_at'] ?? null,
        ];
    }

    public function isDueForCheck(): bool
    {
        $checkedAt = $this->settings->get('update_check')['checked_at'] ?? null;

        if ($checkedAt === null) {
            return true;
        }

        $hours = (int) config('client-reporter.updates.check_interval_hours', 24);

        return now()->parse($checkedAt)->addHours($hours)->isPast();
    }
}
