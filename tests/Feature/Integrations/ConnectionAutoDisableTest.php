<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectorRunner;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectionAutoDisableTest extends TestCase
{
    use RefreshDatabase;

    private function mailchimpConnection(ConnectionStatus $status = ConnectionStatus::Connected): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'mailchimp',
            'status' => $status,
            'credentials' => ['api_key' => 'key-us1'],
            'settings' => ['list_id' => 'abc'],
        ]);
    }

    public function test_a_connection_is_disabled_after_repeated_collection_failures(): void
    {
        config(['client-reporter.collection.failure_threshold' => 3]);
        Http::fake(['us1.api.mailchimp.com/*' => Http::response('down', 500)]);

        $connection = $this->mailchimpConnection();
        $runner = app(CollectorRunner::class);
        $range = DateRange::thisMonth();

        $runner->collectAll($connection, $range);
        $connection->refresh();
        $this->assertSame(1, $connection->consecutive_failures);
        $this->assertSame(ConnectionStatus::NeedsAttention, $connection->status, 'still retrying below the threshold');
        $this->assertNull($connection->disabled_at);

        $runner->collectAll($connection, $range);
        $runner->collectAll($connection, $range);

        $connection->refresh();
        $this->assertSame(3, $connection->consecutive_failures);
        $this->assertSame(ConnectionStatus::Disabled, $connection->status);
        $this->assertNotNull($connection->disabled_at);
    }

    public function test_a_success_resets_the_failure_count_and_re_enables(): void
    {
        config(['client-reporter.collection.failure_threshold' => 3]);
        Http::fake([
            'us1.api.mailchimp.com/3.0/lists/abc' => Http::response(['name' => 'News', 'stats' => ['member_count' => 10]]),
            'us1.api.mailchimp.com/3.0/lists/abc/growth-history*' => Http::response(['history' => []]),
        ]);

        $connection = $this->mailchimpConnection(ConnectionStatus::Disabled);
        $connection->update(['consecutive_failures' => 3, 'disabled_at' => now()]);

        app(CollectorRunner::class)->collectAll($connection, DateRange::thisMonth());

        $connection->refresh();
        $this->assertSame(0, $connection->consecutive_failures);
        $this->assertNull($connection->disabled_at);
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
    }

    public function test_collection_from_a_bare_connection_does_not_trip_lazy_loading(): void
    {
        // The queued job reloads the connection with no relations; the runner
        // must eager-load what collectors need (site URL, workspace credential)
        // or strict mode throws a LazyLoadingViolationException.
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response([
            'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]]],
        ])]);

        $site = Site::factory()->create(['url' => 'https://example.com']);
        SiteIntegration::factory()->for($site)->create(['integration_key' => 'pagespeed', 'status' => ConnectionStatus::Connected]);

        // Re-fetch fresh, exactly as the job does — no relations loaded.
        $bare = SiteIntegration::query()->firstWhere('integration_key', 'pagespeed');
        $this->assertFalse($bare->relationLoaded('site'));

        $runs = app(CollectorRunner::class)->collectAll($bare, DateRange::thisMonth());

        $this->assertSame('success', $runs[0]->status);
        $this->assertSame(ConnectionStatus::Connected, $bare->refresh()->status);
    }

    public function test_the_collect_command_skips_disabled_connections(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $this->mailchimpConnection(ConnectionStatus::Disabled)->update(['disabled_at' => now()]);

        $this->artisan('client-reporter:collect', ['--sync' => true, '--force' => true])->assertSuccessful();

        Http::assertNothingSent();
    }
}
