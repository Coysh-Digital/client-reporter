<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectorRunner;
use App\Integrations\Contracts\Collector;
use App\Integrations\Support\AuthenticationException;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\RateLimitedException;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CollectorRunnerTest extends TestCase
{
    use RefreshDatabase;

    private function collectorThat(?Throwable $throws): Collector
    {
        return new class($throws) implements Collector
        {
            public function __construct(private readonly ?Throwable $throws) {}

            public function key(): string
            {
                return 'probe';
            }

            public function collect(SiteIntegration $connection, DateRange $range): CollectorResult
            {
                if ($this->throws !== null) {
                    throw $this->throws;
                }

                return (new CollectorResult)
                    ->metric('probe.value', 1.0)
                    ->snapshot(['timeseries' => [['date' => '2026-09-01', 'value' => 1]]]);
            }

            public function intervalMinutes(): int
            {
                return 60;
            }
        };
    }

    public function test_a_successful_run_marks_the_connection_connected_and_flags_the_series(): void
    {
        $connection = SiteIntegration::factory()->create([
            'status' => ConnectionStatus::NeedsAttention,
            'last_failure_kind' => 'failed',
            'collection_queued_at' => now(),
        ]);

        $run = app(CollectorRunner::class)->run($connection, $this->collectorThat(null), DateRange::thisMonth());

        $this->assertSame('success', $run->status);
        $connection->refresh();
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertNull($connection->last_failure_kind);
        $this->assertNull($connection->collection_queued_at);
        $this->assertTrue($connection->snapshots()->first()->has_timeseries);
    }

    public function test_rejected_credentials_stop_scheduled_collection(): void
    {
        $connection = SiteIntegration::factory()->create();

        app(CollectorRunner::class)->run($connection, $this->collectorThat(new AuthenticationException('Provider rejected the token.')), DateRange::thisMonth());

        $connection->refresh();
        $this->assertSame(ConnectionStatus::AuthExpired, $connection->status);
        $this->assertSame('auth', $connection->last_failure_kind);
        $this->assertSame('Provider rejected the token.', $connection->last_error);
        $this->assertFalse($connection->status->isLive());
    }

    public function test_rate_limiting_and_other_failures_keep_the_connection_live(): void
    {
        $connection = SiteIntegration::factory()->create();
        $runner = app(CollectorRunner::class);

        $runner->run($connection, $this->collectorThat(new RateLimitedException('Slow down.')), DateRange::thisMonth());
        $this->assertSame(ConnectionStatus::NeedsAttention, $connection->refresh()->status);
        $this->assertSame('rate_limit', $connection->last_failure_kind);

        $runner->run($connection, $this->collectorThat(new IntegrationException('Bad response.')), DateRange::thisMonth());
        $this->assertSame('failed', $connection->refresh()->last_failure_kind);
        $this->assertTrue($connection->status->isLive());
    }

    public function test_unexpected_exceptions_never_leak_their_message(): void
    {
        $connection = SiteIntegration::factory()->create();

        $run = app(CollectorRunner::class)->run($connection, $this->collectorThat(new RuntimeException('token=abc123')), DateRange::thisMonth());

        $this->assertStringNotContainsString('abc123', (string) $run->error_message);
        $this->assertStringContainsString('RuntimeException', (string) $run->error_message);
    }
}
