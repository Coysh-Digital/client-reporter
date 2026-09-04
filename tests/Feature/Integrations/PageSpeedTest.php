<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\PageSpeed\PageSpeedCollector;
use App\Models\MetricSnapshot;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function connection(): SiteIntegration
    {
        $site = Site::factory()->create(['url' => 'https://example.com']);

        return SiteIntegration::factory()->for($site)->create(['integration_key' => 'pagespeed']);
    }

    private function fakePageSpeed(int $score): void
    {
        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => $score / 100]]],
            ]),
        ]);
    }

    public function test_collector_polls_live_and_logs_todays_reading(): void
    {
        $this->fakePageSpeed(88);

        $result = (new PageSpeedCollector)->collect($this->connection(), DateRange::thisMonth());

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(88.0, $metrics['performance.score']->value);
    }

    public function test_collector_reads_all_four_lighthouse_categories(): void
    {
        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => ['categories' => [
                    'performance' => ['score' => 0.91],
                    'accessibility' => ['score' => 0.98],
                    'best-practices' => ['score' => 0.96],
                    'seo' => ['score' => 1.0],
                ]],
            ]),
        ]);

        $metrics = collect((new PageSpeedCollector)->collect($this->connection(), DateRange::thisMonth())->metrics())->keyBy('key');

        $this->assertSame(91.0, $metrics['performance.score']->value);
        $this->assertSame(98.0, $metrics['performance.accessibility']->value);
        $this->assertSame(96.0, $metrics['performance.best_practices']->value);
        $this->assertSame(100.0, $metrics['performance.seo']->value);
    }

    public function test_a_request_for_a_past_period_still_polls_fresh_but_excludes_todays_entry_from_the_chart(): void
    {
        // PageSpeed can only ever report "right now" — there is no historical
        // date-range API — so unlike Uptime Kuma, every call polls fresh
        // regardless of the period asked, matching the pre-existing contract.
        // Only the score-history chart is period-scoped, via the log.
        $this->fakePageSpeed(75);

        $result = (new PageSpeedCollector)->collect($this->connection(), new DateRange('2020-01-01', '2020-01-31'));

        Http::assertSentCount(1);
        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(75.0, $metrics['performance.score']->value);
        $this->assertEmpty($result->snapshotPayload()['timeseries'], "Today's entry falls outside the requested 2020 period.");
    }

    public function test_the_log_accumulates_one_entry_per_day_across_calls(): void
    {
        $connection = $this->connection();

        // Http::fake() with a repeated URL pattern doesn't replace an earlier
        // registration within the same test — a sequence is required so each
        // successive poll gets the next response.
        Http::fakeSequence('www.googleapis.com/pagespeedonline/*')
            ->push(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.70]]]])
            ->push(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.90]]]]);

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 10));
        (new PageSpeedCollector)->collect($connection, DateRange::thisMonth());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 8, 20));
        $result = (new PageSpeedCollector)->collect($connection, DateRange::thisMonth());

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(90.0, $metrics['performance.score']->value, 'The metric is always this call\'s own fresh poll.');

        $series = collect($result->snapshotPayload()['timeseries'])->keyBy('date');
        $this->assertSame(70.0, $series['2026-08-10']['value']);
        $this->assertSame(90.0, $series['2026-08-20']['value']);

        $log = MetricSnapshot::query()->where('collector_key', 'core-web-vitals-log')->first();
        $this->assertCount(2, $log->payload['days']);
    }
}
