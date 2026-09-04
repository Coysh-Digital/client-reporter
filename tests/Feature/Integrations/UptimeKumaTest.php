<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\UptimeKuma\MonitorsCollector;
use App\Integrations\UptimeKuma\UptimeKumaIntegration;
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

class UptimeKumaTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMetrics(string $body): void
    {
        Http::fake([
            'kuma.example.test/*' => Http::response($body, 200, ['Content-Type' => 'text/plain']),
        ]);
    }

    private function connection(): SiteIntegration
    {
        // base_url is a non-secret field, so it belongs in `settings`, not
        // `credentials` — matching what the real connect form actually saves.
        return SiteIntegration::factory()->create([
            'settings' => ['base_url' => 'https://kuma.example.test'],
            'credentials' => ['api_key' => 'valid'],
        ]);
    }

    public function test_verify_succeeds_with_valid_key(): void
    {
        $this->fakeMetrics('monitor_status{monitor_name="Website"} 1');

        $result = (new UptimeKumaIntegration)->verify($this->connection());

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('1 monitor', $result->message);
    }

    public function test_the_api_key_is_sent_as_the_basic_auth_password_not_the_username(): void
    {
        // Per Uptime Kuma's documented API-key auth convention: the key goes
        // in the password slot, and the username is ignored.
        $this->fakeMetrics('monitor_status{monitor_name="Website"} 1');

        (new UptimeKumaIntegration)->verify($this->connection());

        Http::assertSent(function ($request) {
            $header = $request->header('Authorization')[0] ?? '';
            [$user, $pass] = explode(':', base64_decode(str_replace('Basic ', '', $header)), 2);

            return $user === '' && $pass === 'valid';
        });
    }

    public function test_verify_fails_gracefully_when_the_key_is_rejected(): void
    {
        Http::fake(['kuma.example.test/*' => Http::response('', 401)]);

        $result = (new UptimeKumaIntegration)->verify($this->connection());

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rejected', $result->message);
    }

    public function test_collector_polls_live_and_records_an_up_sample_for_the_current_period(): void
    {
        $this->fakeMetrics(<<<'PROM'
            monitor_status{monitor_name="Website"} 1
            monitor_response_time{monitor_name="Website"} 120
            PROM);

        $result = (new MonitorsCollector)->collect($this->connection(), DateRange::thisMonth());

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertEqualsWithDelta(100.0, $metrics['uptime.percentage']->value, 0.001);
        $this->assertSame(0, (int) $metrics['uptime.incidents']->value);
        $this->assertSame(120, (int) $metrics['uptime.response_time_ms']->value);
    }

    public function test_collector_uses_kumas_real_uptime_ratio_and_response_aggregates(): void
    {
        // Newer Kuma exposes real aggregates; the collector should report those
        // (matching the Kuma dashboard) rather than its own rolling average.
        $this->fakeMetrics(<<<'PROM'
            monitor_status{monitor_name="Website"} 1
            monitor_response_time{monitor_name="Website"} 69
            monitor_uptime_ratio{monitor_name="Website",window="1d"} 0.9972222222222222
            monitor_uptime_ratio{monitor_name="Website",window="30d"} 0.9988597208795023
            monitor_uptime_ratio{monitor_name="Website",window="365d"} 0.9988597208795023
            monitor_response_time_seconds{monitor_name="Website",window="1d"} 0.08616
            monitor_response_time_seconds{monitor_name="Website",window="30d"} 0.11466
            PROM);

        $result = (new MonitorsCollector)->collect($this->connection(), DateRange::thisMonth());

        $metrics = collect($result->metrics())->keyBy('key');
        // 30-day window is preferred: 0.99886 -> 99.886%, 0.11466s -> 115ms.
        $this->assertEqualsWithDelta(99.886, $metrics['uptime.percentage']->value, 0.001);
        $this->assertSame(115, (int) $metrics['uptime.response_time_ms']->value);
        $this->assertSame(1, (int) $metrics['uptime.monitors']->value);
        // Downtime is derived from the ratio, so it's non-zero despite no
        // outage being observed in-sample.
        $this->assertGreaterThan(0, (int) $metrics['uptime.downtime_seconds']->value);
    }

    public function test_collector_detects_a_down_then_up_transition_as_one_incident(): void
    {
        $connection = $this->connection();

        // Http::fake() with a repeated URL pattern doesn't replace an earlier
        // registration within the same test — a sequence is required so each
        // successive poll gets the next response.
        Http::fakeSequence('kuma.example.test/*')
            ->push('monitor_status{monitor_name="Website"} 1', 200)
            ->push('monitor_status{monitor_name="Website"} 0', 200)
            ->push('monitor_status{monitor_name="Website"} 1', 200);

        (new MonitorsCollector)->collect($connection, DateRange::thisMonth());
        $result = (new MonitorsCollector)->collect($connection, DateRange::thisMonth());

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(1, (int) $metrics['uptime.incidents']->value);
        $payload = $result->snapshotPayload();
        $this->assertSame('Website', $payload['incidents'][0]['monitor']);

        (new MonitorsCollector)->collect($connection, DateRange::thisMonth());

        $log = MetricSnapshot::query()->where('collector_key', 'monitors_log')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->payload['incidents'][0]['ended_at'], 'The incident should be closed once the monitor recovers.');
    }

    public function test_collector_reports_a_daily_uptime_timeseries(): void
    {
        $this->fakeMetrics('monitor_status{monitor_name="Website"} 1');

        $result = (new MonitorsCollector)->collect($this->connection(), DateRange::thisMonth());

        $today = CarbonImmutable::now()->toDateString();
        $series = collect($result->snapshotPayload()['timeseries'])->keyBy('date');
        $this->assertEqualsWithDelta(100.0, $series[$today]['value'], 0.001);
    }

    public function test_a_fully_elapsed_past_period_does_not_poll_live_and_has_no_data(): void
    {
        Http::fake();
        $connection = $this->connection();

        $result = (new MonitorsCollector)->collect($connection, new DateRange('2020-01-01', '2020-01-31'));

        Http::assertNothingSent();

        $metrics = collect($result->metrics())->keyBy('key');
        $this->assertSame(0.0, $metrics['uptime.percentage']->value);
        $this->assertSame(0, (int) $metrics['uptime.monitors']->value);
    }

    public function test_uptime_kuma_supports_workspace_scope(): void
    {
        $fields = collect((new UptimeKumaIntegration)->accountConfigFields())->pluck('key');

        $this->assertTrue((new UptimeKumaIntegration)->supportsWorkspaceScope());
        $this->assertContains('base_url', $fields);
        $this->assertContains('api_key', $fields);
        $this->assertNotContains('monitors', $fields);
    }

    public function test_discover_connections_lists_every_monitor_by_name(): void
    {
        $this->fakeMetrics(<<<'PROM'
            monitor_status{monitor_name="Northwind",monitor_url="https://northwind.test"} 1
            monitor_status{monitor_name="Acme",monitor_url="https://acme.test"} 1
            PROM);

        $workspace = new WorkspaceIntegration([
            'integration_key' => 'uptime_kuma',
            'settings' => ['base_url' => 'https://kuma.example.test'],
            'credentials' => ['api_key' => 'workspace-key'],
        ]);

        $discovered = (new UptimeKumaIntegration)->discoverConnections($workspace);

        $this->assertCount(2, $discovered);
        $this->assertSame('Northwind', $discovered[0]->externalId);
        $this->assertSame('https://northwind.test', $discovered[0]->url);
        $this->assertSame('Northwind', $discovered[0]->settings['monitors']);
    }

    public function test_client_for_falls_back_to_the_workspace_base_url_when_the_site_has_none(): void
    {
        // A workspace-mapped connection's own `settings` only ever carry the
        // matched monitor name (per DiscoveredConnection::$settings) — unlike
        // `credentials`, SiteIntegration::setting() has no built-in workspace
        // fallback, so clientFor() must resolve base_url itself.
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'uptime_kuma',
            'name' => 'Uptime Kuma (workspace)',
            'status' => ConnectionStatus::Connected,
            'settings' => ['base_url' => 'https://kuma.example.test'],
            'credentials' => ['api_key' => 'workspace-key'],
        ]);

        $connection = SiteIntegration::factory()->create([
            'workspace_integration_id' => $workspace->id,
            'settings' => ['monitors' => 'Northwind'],
            'credentials' => null,
        ]);

        $this->fakeMetrics('monitor_status{monitor_name="Northwind"} 1');

        $result = (new MonitorsCollector)->collect($connection, DateRange::thisMonth());

        $this->assertEqualsWithDelta(100.0, collect($result->metrics())->firstWhere('key', 'uptime.percentage')->value, 0.001);
    }

    public function test_workspace_connect_auto_matches_and_creates_working_site_connections(): void
    {
        $this->fakeMetrics(<<<'PROM'
            monitor_status{monitor_name="Northwind",monitor_url="https://northwind.test"} 1
            monitor_status{monitor_name="Orphan",monitor_url="https://nowhere.test"} 1
            PROM);

        $manager = User::factory()->manager()->create();
        $northwind = Site::factory()->create(['url' => 'https://northwind.test']);

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['key' => 'uptime_kuma'])
            ->set('values.base_url', 'https://kuma.example.test')
            ->set('values.api_key', 'workspace-key')
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertSet('assignments.0', $northwind->id)
            ->assertSet('assignments.1', '')
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $workspace = WorkspaceIntegration::query()->firstWhere('integration_key', 'uptime_kuma');
        $this->assertNotNull($workspace);

        $connection = SiteIntegration::query()
            ->where('site_id', $northwind->id)->where('integration_key', 'uptime_kuma')->first();
        $this->assertNotNull($connection);
        $this->assertSame('Northwind', $connection->setting('monitors'));

        // The end-to-end point of this test: a per-site connection created
        // purely via workspace mapping (no local base_url) can still poll.
        $result = (new MonitorsCollector)->collect($connection, DateRange::thisMonth());
        $this->assertEqualsWithDelta(100.0, collect($result->metrics())->firstWhere('key', 'uptime.percentage')->value, 0.001);
    }
}
