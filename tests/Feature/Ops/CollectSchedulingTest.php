<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\ConnectionStatus;
use App\Jobs\RunConnectorCollection;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CollectSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function connection(array $attributes = []): SiteIntegration
    {
        return SiteIntegration::factory()->create(array_merge([
            'status' => ConnectionStatus::Connected,
        ], $attributes));
    }

    public function test_a_due_connection_queues_the_current_month_only(): void
    {
        Queue::fake();
        $connection = $this->connection(['last_attempted_at' => now()->subHours(7)]);

        $this->artisan('client-reporter:collect')->assertSuccessful();

        // Exactly one job — the current month — not one per warm period.
        Queue::assertPushed(RunConnectorCollection::class, 1);
        Queue::assertPushed(
            RunConnectorCollection::class,
            fn (RunConnectorCollection $job): bool => $job->siteIntegration->is($connection)
                && $job->periodStart === DateRange::thisMonth()->start->toDateString(),
        );
    }

    public function test_a_recently_attempted_connection_is_not_recollected(): void
    {
        Queue::fake();
        $this->connection(['last_attempted_at' => now()->subMinutes(5)]);

        $this->artisan('client-reporter:collect')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_failing_connection_backs_off_and_is_not_retried_every_tick(): void
    {
        Queue::fake();
        // A connection that just failed: attempt recorded, never a success.
        $this->connection([
            'status' => ConnectionStatus::NeedsAttention,
            'last_attempted_at' => now()->subMinutes(5),
            'last_collected_at' => null,
            'last_error' => 'Access denied.',
        ]);

        $this->artisan('client-reporter:collect')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_history_mode_collects_the_previous_month_ignoring_the_interval(): void
    {
        Queue::fake();
        // Recently attempted, so it is not due for the current month…
        $connection = $this->connection(['last_attempted_at' => now()]);

        $this->artisan('client-reporter:collect', ['--history' => true])->assertSuccessful();

        // …but history mode still refreshes the completed previous month.
        Queue::assertPushed(
            RunConnectorCollection::class,
            fn (RunConnectorCollection $job): bool => $job->siteIntegration->is($connection)
                && $job->periodStart === DateRange::lastMonth()->start->toDateString(),
        );
    }
}
