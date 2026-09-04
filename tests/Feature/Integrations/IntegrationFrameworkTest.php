<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectorRunner;
use App\Integrations\Contracts\AbstractCollector;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\CollectorResult;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\UptimeRobot\UptimeRobotIntegration;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IntegrationFrameworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_finds_configured_integrations(): void
    {
        $registry = app(IntegrationRegistry::class);

        $this->assertTrue($registry->has('uptimerobot'));
        $this->assertInstanceOf(UptimeRobotIntegration::class, $registry->find('uptimerobot'));
    }

    public function test_registry_groups_by_category(): void
    {
        $grouped = app(IntegrationRegistry::class)->byCategory();

        $this->assertArrayHasKey(IntegrationCategory::Monitoring->value, $grouped);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $connection = SiteIntegration::factory()->create([
            'credentials' => ['api_key' => 'super-secret-value'],
        ]);

        $raw = (string) $connection->newQuery()->getConnection()
            ->table('site_integrations')->where('id', $connection->id)->value('credentials');

        // Stored value must not contain the plaintext secret.
        $this->assertStringNotContainsString('super-secret-value', $raw);

        // But the model decrypts it transparently.
        $this->assertSame('super-secret-value', $connection->fresh()->credential('api_key'));
    }

    public function test_collector_runner_persists_metrics_and_snapshot(): void
    {
        $connection = SiteIntegration::factory()->create(['status' => ConnectionStatus::NotConnected]);

        $collector = new class extends AbstractCollector
        {
            public function key(): string
            {
                return 'demo';
            }

            public function collect($connection, DateRange $range): CollectorResult
            {
                return CollectorResult::make()
                    ->metric('demo.value', 42, 'units')
                    ->snapshot(['rows' => [1, 2, 3]]);
            }
        };

        $range = new DateRange('2026-08-01', '2026-08-31');
        app(CollectorRunner::class)->run($connection, $collector, $range);

        $this->assertDatabaseHas('metrics', [
            'site_integration_id' => $connection->id,
            'metric_key' => 'demo.value',
            'value' => 42,
        ]);
        $this->assertDatabaseHas('metric_snapshots', [
            'site_integration_id' => $connection->id,
            'collector_key' => 'demo',
        ]);
        $this->assertDatabaseHas('collector_runs', ['status' => 'success']);
        $this->assertSame(ConnectionStatus::Connected, $connection->fresh()->status);
    }

    public function test_recollection_upserts_instead_of_duplicating(): void
    {
        $connection = SiteIntegration::factory()->create();
        $range = new DateRange('2026-08-01', '2026-08-31');

        $value = 10;
        $collector = new class($value) extends AbstractCollector
        {
            public function __construct(private int $value) {}

            public function key(): string
            {
                return 'demo';
            }

            public function collect($connection, DateRange $range): CollectorResult
            {
                return CollectorResult::make()->metric('demo.value', $this->value);
            }
        };

        app(CollectorRunner::class)->run($connection, $collector, $range);
        app(CollectorRunner::class)->run($connection, new class(99) extends AbstractCollector
        {
            public function __construct(private int $value) {}

            public function key(): string
            {
                return 'demo';
            }

            public function collect($connection, DateRange $range): CollectorResult
            {
                return CollectorResult::make()->metric('demo.value', $this->value);
            }
        }, $range);

        $this->assertSame(1, $connection->metrics()->where('metric_key', 'demo.value')->count());
        $this->assertEquals(99.0, $connection->metrics()->where('metric_key', 'demo.value')->value('value'));
    }

    public function test_integration_failure_marks_needs_attention_with_safe_message(): void
    {
        $connection = SiteIntegration::factory()->create();

        $collector = new class extends AbstractCollector
        {
            public function key(): string
            {
                return 'demo';
            }

            public function collect($connection, DateRange $range): CollectorResult
            {
                throw new IntegrationException('Your API key has expired.');
            }
        };

        app(CollectorRunner::class)->run($connection, $collector, new DateRange('2026-08-01', '2026-08-31'));

        $connection->refresh();
        $this->assertSame(ConnectionStatus::NeedsAttention, $connection->status);
        $this->assertSame('Your API key has expired.', $connection->last_error);
        // The attempt is recorded even though it failed, so the due check backs
        // the connection off instead of retrying it on every scheduler tick.
        $this->assertNotNull($connection->last_attempted_at);
        $this->assertNull($connection->last_collected_at);
    }

    public function test_unexpected_errors_do_not_leak_internals(): void
    {
        $connection = SiteIntegration::factory()->create();

        $collector = new class extends AbstractCollector
        {
            public function key(): string
            {
                return 'demo';
            }

            public function collect($connection, DateRange $range): CollectorResult
            {
                throw new RuntimeException('https://api.example.com?token=SECRET123 failed');
            }
        };

        app(CollectorRunner::class)->run($connection, $collector, new DateRange('2026-08-01', '2026-08-31'));

        $this->assertStringNotContainsString('SECRET123', (string) $connection->fresh()->last_error);
    }
}
