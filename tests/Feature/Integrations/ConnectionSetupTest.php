<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Livewire\Integrations\Setup;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_connect_uptimerobot(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response(['stat' => 'ok', 'monitors' => [['id' => 1]]])]);

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'uptimerobot'])
            ->set('values.api_key', 'ur-secret-key')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('sites.show', $site));

        $connection = SiteIntegration::query()->first();
        $this->assertNotNull($connection);
        $this->assertSame(ConnectionStatus::Connected, $connection->status);
        $this->assertSame('ur-secret-key', $connection->credential('api_key'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'integration.connected']);
    }

    public function test_a_bad_key_surfaces_an_error_and_marks_the_connection(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response(['stat' => 'fail', 'error' => ['message' => 'api_key is invalid']])]);

        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'uptimerobot'])
            ->set('values.api_key', 'wrong')
            ->call('save')
            ->assertHasErrors('verification')
            ->assertNoRedirect();

        $this->assertSame(ConnectionStatus::Error, SiteIntegration::query()->first()->status);
    }

    public function test_editing_keeps_the_secret_when_left_blank(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response(['stat' => 'ok', 'monitors' => []])]);

        $manager = User::factory()->manager()->create();
        $connection = SiteIntegration::factory()->create(['credentials' => ['api_key' => 'original-key']]);

        Livewire::actingAs($manager)->test(Setup::class, ['connection' => $connection])
            ->set('name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $connection->refresh();
        $this->assertSame('Renamed', $connection->name);
        $this->assertSame('original-key', $connection->credential('api_key'));
    }

    public function test_a_viewer_cannot_reach_the_connect_screen(): void
    {
        $viewer = User::factory()->viewer()->create();
        $site = Site::factory()->create();

        $this->actingAs($viewer)
            ->get(route('sites.integrations.connect', ['site' => $site, 'key' => 'uptimerobot']))
            ->assertForbidden();
    }

    public function test_scheduled_collect_command_gathers_data_for_due_connections(): void
    {
        Http::fake(['api.uptimerobot.com/*' => Http::response([
            'stat' => 'ok',
            'monitors' => [['id' => 1, 'friendly_name' => 'Site', 'status' => 2, 'custom_uptime_ranges' => '100.0000']],
        ])]);

        $connection = SiteIntegration::factory()->create([
            'status' => ConnectionStatus::Connected,
            'last_collected_at' => null,
        ]);

        $this->artisan('client-reporter:collect', ['--sync' => true])->assertSuccessful();

        $this->assertDatabaseHas('metrics', [
            'site_integration_id' => $connection->id,
            'metric_key' => 'uptime.percentage',
        ]);
    }
}
