<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Support\DiscoveredConnection;
use App\Integrations\UptimeRobot\UptimeRobotIntegration;
use App\Livewire\Integrations\Setup;
use App\Livewire\Integrations\WorkspaceSetup;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use App\Support\SiteMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeUptimeRobot(): void
    {
        Http::fake([
            '*api.uptimerobot.com*' => Http::response(['stat' => 'ok', 'monitors' => [
                ['id' => 111, 'friendly_name' => 'Northwind', 'url' => 'https://northwind.test', 'status' => 2],
                ['id' => 222, 'friendly_name' => 'Acme', 'url' => 'https://www.acme.test/', 'status' => 2],
                ['id' => 333, 'friendly_name' => 'Orphan', 'url' => 'https://nowhere.test', 'status' => 2],
            ]]),
        ]);
    }

    public function test_account_fields_exclude_the_per_site_selector(): void
    {
        $fields = collect((new UptimeRobotIntegration)->accountConfigFields())->pluck('key');

        $this->assertTrue((new UptimeRobotIntegration)->supportsWorkspaceScope());
        $this->assertContains('api_key', $fields);
        $this->assertNotContains('monitors', $fields);
    }

    public function test_site_matcher_matches_by_host_ignoring_scheme_and_www(): void
    {
        $sites = Site::factory()->createMany([
            ['url' => 'https://northwind.test'],
            ['url' => 'http://acme.test/shop'],
        ]);

        $discovered = [
            new DiscoveredConnection('1', 'A', 'https://www.northwind.test/status'),
            new DiscoveredConnection('2', 'B', 'https://acme.test'),
            new DiscoveredConnection('3', 'C', 'https://elsewhere.test'),
        ];

        $matches = SiteMatcher::match($discovered, $sites);

        $this->assertSame($sites[0]->id, $matches[0]);
        $this->assertSame($sites[1]->id, $matches[1]);
        $this->assertNull($matches[2]);
    }

    public function test_workspace_connect_auto_matches_and_creates_site_connections(): void
    {
        $this->fakeUptimeRobot();
        $manager = User::factory()->manager()->create();
        $northwind = Site::factory()->create(['url' => 'https://northwind.test']);
        $acme = Site::factory()->create(['url' => 'https://acme.test']);

        Livewire::actingAs($manager)->test(WorkspaceSetup::class, ['key' => 'uptimerobot'])
            ->set('values.api_key', 'ur_workspace_key')
            ->call('connect')
            ->assertSet('phase', 'mapping')
            ->assertSet('assignments.0', $northwind->id)
            ->assertSet('assignments.1', $acme->id)
            ->assertSet('assignments.2', '')
            ->call('confirm')
            ->assertRedirect(route('integrations.index'));

        $workspace = WorkspaceIntegration::query()->firstWhere('integration_key', 'uptimerobot');
        $this->assertNotNull($workspace);
        $this->assertSame(ConnectionStatus::Connected, $workspace->status);

        // Two matched sites got connections; the orphan monitor was skipped.
        $this->assertSame(2, SiteIntegration::query()->where('integration_key', 'uptimerobot')->count());

        $connection = SiteIntegration::query()
            ->where('site_id', $northwind->id)->where('integration_key', 'uptimerobot')->first();
        $this->assertNotNull($connection);
        $this->assertSame($workspace->id, $connection->workspace_integration_id);
        $this->assertSame('111', $connection->setting('monitors'));
        // Credentials are borrowed from the workspace connection.
        $this->assertSame('ur_workspace_key', $connection->credential('api_key'));
    }

    public function test_credential_falls_back_to_workspace_but_local_wins(): void
    {
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'uptimerobot',
            'name' => 'UptimeRobot (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'workspace_key'],
        ]);

        $linked = SiteIntegration::factory()->create([
            'integration_key' => 'uptimerobot',
            'workspace_integration_id' => $workspace->id,
            'credentials' => null,
        ]);
        $this->assertSame('workspace_key', $linked->credential('api_key'));

        $own = SiteIntegration::factory()->create([
            'integration_key' => 'uptimerobot',
            'workspace_integration_id' => $workspace->id,
            'credentials' => ['api_key' => 'site_key'],
        ]);
        $this->assertSame('site_key', $own->credential('api_key'));
    }

    public function test_per_site_edit_screen_hides_account_fields_for_a_workspace_linked_connection(): void
    {
        $this->fakeUptimeRobot();
        $manager = User::factory()->manager()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'uptimerobot',
            'name' => 'UptimeRobot (workspace)',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'workspace_key'],
        ]);
        $connection = SiteIntegration::factory()->create([
            'integration_key' => 'uptimerobot',
            'workspace_integration_id' => $workspace->id,
            'credentials' => null,
            'settings' => ['monitors' => '111'],
        ]);

        Livewire::actingAs($manager)->test(Setup::class, ['connection' => $connection])
            ->assertSee('Connected via the workspace')
            ->assertSee('Monitor IDs')
            ->assertDontSee('API key')
            ->set('values.monitors', '222')
            ->call('save');

        $connection->refresh();
        $this->assertSame('222', $connection->setting('monitors'));
        // The workspace credential is untouched and never duplicated locally.
        $this->assertArrayNotHasKey('api_key', $connection->credentials ?? []);
        $this->assertSame('workspace_key', $connection->credential('api_key'));
    }
}
