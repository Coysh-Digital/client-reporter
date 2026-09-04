<?php

declare(strict_types=1);

namespace App\Integrations\PageSpeed;

use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\Support\CollectorResult;
use App\Models\MetricSnapshot;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * Collects Core Web Vitals + a performance score for a site's URL from Google
 * PageSpeed Insights. Prefers CrUX field data (real users); falls back to
 * Lighthouse lab data when the URL has no field data yet.
 *
 * PageSpeed only ever answers "what is this URL's performance right now" —
 * there is no historical, query-by-date-range API — so every call polls fresh
 * and reports today's reading as the period's headline metrics, whatever
 * period was asked for (this is a snapshot of current site health, not a
 * historical fact about that period). Additionally, since this collector
 * already only runs once a day, each poll also appends today's reading to its
 * own day-by-day log (reusing MetricSnapshot, not a new model — see Uptime
 * Kuma's MonitorsCollector for the same pattern), which is what powers the
 * score-history chart.
 */
class PageSpeedCollector extends AbstractCollector
{
    private const LOG_COLLECTOR_KEY = 'core-web-vitals-log';

    private const LOG_PERIOD_START = '2000-01-01';

    private const LOG_PERIOD_END = '2099-12-31';

    private const MAX_LOG_AGE_DAYS = 400;

    public function key(): string
    {
        return 'core-web-vitals';
    }

    public function intervalMinutes(): int
    {
        // PageSpeed is a heavier, rate-limited call; once a day is plenty.
        return 1440;
    }

    public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
    {
        $client = new PageSpeedClient((string) $connection->credential('api_key') ?: null);
        $strategy = (string) ($connection->setting('strategy') ?: 'mobile');

        $data = $client->analyze($connection->site->url, $strategy);

        $field = (array) ($data['loadingExperience']['metrics'] ?? []);
        $lighthouse = (array) ($data['lighthouseResult'] ?? []);
        $audits = (array) ($lighthouse['audits'] ?? []);

        $score = isset($lighthouse['categories']['performance']['score'])
            ? (float) round(((float) $lighthouse['categories']['performance']['score']) * 100)
            : null;

        $lcp = $field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? ($audits['largest-contentful-paint']['numericValue'] ?? null);
        $inp = $field['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? ($audits['interaction-to-next-paint']['numericValue'] ?? null);
        $clsRaw = $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ?? null;
        $cls = $clsRaw !== null ? ((float) $clsRaw) / 100 : ($audits['cumulative-layout-shift']['numericValue'] ?? null);
        $fcp = $field['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? ($audits['first-contentful-paint']['numericValue'] ?? null);
        $ttfb = $field['EXPERIENCE_TIME_TO_FIRST_BYTE']['percentile'] ?? null;

        $source = $field !== [] ? 'field' : 'lab';

        $log = $this->loadLog($connection);
        $log[CarbonImmutable::now()->toDateString()] = ['score' => $score];
        $this->prune($log);
        $this->saveLog($connection, $log);

        $result = CollectorResult::make();

        if ($score !== null) {
            $result->metric('performance.score', $score);
        }
        if ($lcp !== null) {
            $result->metric('performance.lcp_ms', (float) $lcp, 'ms');
        }
        if ($inp !== null) {
            $result->metric('performance.inp_ms', (float) $inp, 'ms');
        }
        if ($cls !== null) {
            $result->metric('performance.cls', (float) $cls);
        }
        if ($fcp !== null) {
            $result->metric('performance.fcp_ms', (float) $fcp, 'ms');
        }
        if ($ttfb !== null) {
            $result->metric('performance.ttfb_ms', (float) $ttfb, 'ms');
        }

        return $result->snapshot([
            'source' => $source,
            'strategy' => $strategy,
            'overall' => $data['loadingExperience']['overall_category'] ?? null,
            'timeseries' => $this->timeseriesInRange($log, $range),
        ]);
    }

    /**
     * @return array<string, array{score: ?float}>
     */
    private function loadLog(SiteIntegration $connection): array
    {
        $payload = MetricSnapshot::query()
            ->where('site_integration_id', $connection->id)
            ->where('collector_key', self::LOG_COLLECTOR_KEY)
            ->whereDate('period_start', self::LOG_PERIOD_START)
            ->whereDate('period_end', self::LOG_PERIOD_END)
            ->first()?->payload;

        return $payload['days'] ?? [];
    }

    private function saveLog(SiteIntegration $connection, array $log): void
    {
        MetricSnapshot::query()->updateOrCreate(
            [
                'site_integration_id' => $connection->id,
                'collector_key' => self::LOG_COLLECTOR_KEY,
                'period_start' => CarbonImmutable::parse(self::LOG_PERIOD_START)->startOfDay(),
                'period_end' => CarbonImmutable::parse(self::LOG_PERIOD_END)->startOfDay(),
            ],
            ['granularity' => 'range', 'payload' => ['days' => $log], 'captured_at' => now()],
        );
    }

    /**
     * @param  array<string, array{score: ?float}>  $log
     * @return array<int, array{date: string, value: float}>
     */
    private function timeseriesInRange(array $log, DateRange $range): array
    {
        ksort($log);

        $series = [];
        foreach ($log as $day => $entry) {
            if ($range->contains(CarbonImmutable::parse($day)) && $entry['score'] !== null) {
                $series[] = ['date' => $day, 'value' => (float) $entry['score']];
            }
        }

        return $series;
    }

    private function prune(array &$log): void
    {
        $cutoff = CarbonImmutable::now()->subDays(self::MAX_LOG_AGE_DAYS)->toDateString();

        $log = array_filter($log, fn (string $day): bool => $day >= $cutoff, ARRAY_FILTER_USE_KEY);
    }
}
