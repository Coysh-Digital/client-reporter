<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectionSchedule;
use App\Models\CollectorRun;
use App\Models\SiteIntegration;
use App\Support\ConnectionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConnectionStateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: string}>
     */
    public static function states(): array
    {
        return [
            'connected' => [['status' => ConnectionStatus::Connected], 'Connected', 'ok', 'collect'],
            'auth expired' => [['status' => ConnectionStatus::AuthExpired, 'last_error' => 'Token revoked'], 'Authentication expired', 'danger', 'reconnect'],
            'error' => [['status' => ConnectionStatus::Error, 'last_error' => 'HTTP 500'], 'Attention required', 'danger', 'retry'],
            'rate limited' => [['status' => ConnectionStatus::NeedsAttention, 'last_failure_kind' => 'rate_limit'], 'Rate limited', 'warn', 'retry'],
            'failed sync' => [['status' => ConnectionStatus::NeedsAttention, 'last_failure_kind' => 'failed'], 'Last sync failed', 'warn', 'retry'],
            'not connected' => [['status' => ConnectionStatus::NotConnected], 'Not connected', 'neutral', 'connect'],
            'recently queued' => [['status' => ConnectionStatus::Connected, 'collection_queued_at' => now()->subMinutes(2)], 'Syncing', 'info', 'wait'],
            'stale queue marker' => [['status' => ConnectionStatus::Connected, 'collection_queued_at' => now()->subHours(2)], 'Connected', 'ok', 'collect'],
            'queued then attempted' => [['status' => ConnectionStatus::Error, 'collection_queued_at' => now()->subMinutes(5), 'last_attempted_at' => now()->subMinute(), 'last_error' => 'x'], 'Attention required', 'danger', 'retry'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('states')]
    public function test_it_maps_stored_state_to_one_vocabulary(array $attributes, string $label, string $variant, string $action): void
    {
        $connection = SiteIntegration::factory()->create($attributes)->load('latestRun');

        $state = ConnectionState::from($connection, app(CollectionSchedule::class));

        $this->assertSame($label, $state->label);
        $this->assertSame($variant, $state->variant);
        $this->assertSame($action, $state->action);
        $this->assertSame($label === 'Syncing', $state->syncing);
    }

    public function test_a_running_collector_run_means_syncing_until_it_goes_stale(): void
    {
        $connection = SiteIntegration::factory()->create(['status' => ConnectionStatus::Connected]);
        $run = CollectorRun::query()->create([
            'site_integration_id' => $connection->id,
            'collector_key' => 'monitors',
            'status' => 'running',
            'started_at' => now()->subMinutes(3),
        ]);

        $this->assertTrue(ConnectionState::from($connection->load('latestRun'), app(CollectionSchedule::class))->syncing);

        $run->update(['started_at' => now()->subMinutes(ConnectionState::RUNNING_STALE_MINUTES + 1)]);
        $this->assertFalse(ConnectionState::from($connection->fresh()->load('latestRun'), app(CollectionSchedule::class))->syncing);
    }

    public function test_detail_and_timing_describe_the_row(): void
    {
        $connection = SiteIntegration::factory()->create([
            'status' => ConnectionStatus::Error,
            'last_error' => 'Could not reach UptimeRobot.',
            'last_collected_at' => now()->subHours(3),
            'last_attempted_at' => now()->subMinutes(10),
        ]);

        $state = ConnectionState::from($connection->load('latestRun'), app(CollectionSchedule::class));

        $this->assertSame('Could not reach UptimeRobot.', $state->detail);
        $this->assertStringStartsWith('Last collected 3 hours ago', $state->timing());
        $this->assertNull($state->nextDueAt, 'A connection in error is not on the schedule.');

        $healthy = SiteIntegration::factory()->create(['status' => ConnectionStatus::Connected, 'last_attempted_at' => now()->subMinutes(10)]);
        $timing = ConnectionState::from($healthy->load('latestRun'), app(CollectionSchedule::class))->timing();
        $this->assertStringContainsString('next due', $timing);
    }
}
