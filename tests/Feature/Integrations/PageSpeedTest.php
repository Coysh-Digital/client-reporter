<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\PageSpeed\PageSpeedCollector;
use App\Integrations\PageSpeed\PageSpeedIntegration;
use App\Livewire\Integrations\Setup;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\MetricSnapshot;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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

    public function test_pagespeed_connects_at_the_workspace_level_across_sites(): void
    {
        $manager = User::factory()->manager()->create();
        $northwind = Site::factory()->create(['name' => 'Northwind', 'url' => 'https://northwind.test']);
        Site::factory()->create(['name' => 'Inactive', 'url' => 'https://inactive.test', 'is_active' => false]);

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['key' => 'pagespeed'])
            ->set('values.api_key', 'shared-key')
            ->call('connect')
            ->assertSet('phase', 'mapping')
            // Only the active site is discovered, matched to itself by URL.
            ->assertSet('assignments.0', $northwind->id)
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $workspace = WorkspaceIntegration::query()->firstWhere('integration_key', 'pagespeed');
        $this->assertNotNull($workspace);

        $connection = SiteIntegration::query()
            ->where('site_id', $northwind->id)->where('integration_key', 'pagespeed')->first();
        $this->assertNotNull($connection);
        $this->assertSame($workspace->id, $connection->workspace_integration_id);
        // The per-site connection borrows the workspace's API key.
        $this->assertSame('shared-key', $connection->credential('api_key'));
    }

    public function test_pagespeed_discovers_only_active_sites(): void
    {
        Site::factory()->create(['url' => 'https://a.test']);
        Site::factory()->create(['url' => 'https://b.test', 'is_active' => false]);

        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'pagespeed',
            'name' => 'PageSpeed (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'k'],
        ]);

        $discovered = (new PageSpeedIntegration)->discoverConnections($workspace);

        $this->assertCount(1, $discovered);
        $this->assertSame('https://a.test', $discovered[0]->url);
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

    public function test_api_key_falls_back_to_the_workspace_connection_when_a_site_connection_is_not_linked(): void
    {
        // A standalone per-site connection: no key of its own, not linked to a
        // workspace connection.
        $connection = $this->connection();
        $connection->update(['credentials' => null]);
        $connection = $connection->fresh();
        $this->assertNull($connection->workspace_integration_id);

        // The key was entered once at the workspace level.
        WorkspaceIntegration::query()->create([
            'integration_key' => 'pagespeed',
            'name' => 'PageSpeed (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'WS-KEY-123'],
        ]);

        // The resolved key is the workspace one, so the call is authenticated
        // rather than hitting Google anonymously and being rate-limited.
        $this->assertSame('WS-KEY-123', PageSpeedIntegration::apiKeyFor($connection));

        // And collection runs cleanly using it.
        $this->fakePageSpeed(90);
        $result = (new PageSpeedCollector)->collect($connection, new DateRange('2026-08-01', '2026-08-31'));
        $this->assertSame(90.0, collect($result->metrics())->firstWhere('key', 'performance.score')->value);
    }

    public function test_api_key_falls_back_to_a_key_stored_on_any_other_connection(): void
    {
        // The connection being collected has no key and no workspace link.
        $connection = $this->connection();
        $connection->update(['credentials' => null]);

        // Another site's PageSpeed connection carries a key. Since the key is a
        // single workspace-wide Google key, it's reused here.
        $other = Site::factory()->create(['url' => 'https://other.example']);
        SiteIntegration::factory()->for($other)->create([
            'integration_key' => 'pagespeed',
            'credentials' => ['api_key' => 'SHARED-KEY'],
        ]);

        $this->assertSame('SHARED-KEY', PageSpeedIntegration::apiKeyFor($connection->fresh()));
    }

    public function test_a_site_connections_own_key_takes_precedence_over_the_workspace_key(): void
    {
        $connection = $this->connection();
        $connection->update(['credentials' => ['api_key' => 'SITE-KEY']]);

        WorkspaceIntegration::query()->create([
            'integration_key' => 'pagespeed',
            'name' => 'PageSpeed (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'WS-KEY'],
        ]);

        $this->assertSame('SITE-KEY', PageSpeedIntegration::apiKeyFor($connection->fresh()));
    }

    public function test_api_key_source_reports_where_the_key_comes_from(): void
    {
        $connection = $this->connection();

        // Its own key.
        $connection->update(['credentials' => ['api_key' => 'SITE']]);
        $this->assertSame('own', PageSpeedIntegration::apiKeySource($connection->fresh()));

        // No key anywhere.
        $connection->update(['credentials' => null]);
        $this->assertSame('none', PageSpeedIntegration::apiKeySource($connection->fresh()));

        // Falls back to the workspace key.
        WorkspaceIntegration::query()->create([
            'integration_key' => 'pagespeed',
            'name' => 'PageSpeed (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'WS'],
        ]);
        $this->assertSame('workspace', PageSpeedIntegration::apiKeySource($connection->fresh()));
    }

    public function test_connecting_a_site_that_already_has_the_integration_updates_it_instead_of_duplicating(): void
    {
        $this->fakePageSpeed(90);

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create(['url' => 'https://example.com']);
        // An existing connection (e.g. discovered earlier by the workspace flow).
        SiteIntegration::factory()->for($site)->create(['integration_key' => 'pagespeed', 'credentials' => null]);

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'pagespeed'])
            ->set('values.strategy', 'desktop')
            ->call('save')
            ->assertHasNoErrors();

        // Still one connection — updated, not a second row that trips the unique key.
        $connections = SiteIntegration::query()->where('site_id', $site->id)->where('integration_key', 'pagespeed')->get();
        $this->assertCount(1, $connections);
        $this->assertSame('desktop', $connections->first()->setting('strategy'));
    }
}
