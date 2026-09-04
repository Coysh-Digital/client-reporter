<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When Google OAuth isn't configured (no GOOGLE_CLIENT_ID/SECRET), starting a
 * Google connect flow must redirect back with a clear message rather than a
 * bare 404 — this was silently 404ing until fixed.
 */
class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    }

    public function test_site_connect_redirects_with_a_message_when_google_oauth_is_not_configured(): void
    {
        $manager = User::factory()->manager()->create();
        $connection = SiteIntegration::factory()->create(['integration_key' => 'google_analytics']);

        $this->actingAs($manager)
            ->get(route('integrations.google.connect', $connection))
            ->assertRedirect(route('integrations.edit', $connection))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'GOOGLE_CLIENT_ID'));
    }

    public function test_workspace_connect_redirects_with_a_message_when_google_oauth_is_not_configured(): void
    {
        $manager = User::factory()->manager()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'google_analytics',
            'name' => 'Google Analytics (workspace)',
            'status' => ConnectionStatus::NotConnected,
        ]);

        $this->actingAs($manager)
            ->get(route('integrations.workspace.google.connect', $workspace))
            ->assertRedirect(route('integrations.workspace.edit', $workspace))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'GOOGLE_CLIENT_ID'));
    }

    public function test_site_connect_proceeds_to_google_once_configured(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $manager = User::factory()->manager()->create();
        $connection = SiteIntegration::factory()->create(['integration_key' => 'google_analytics']);

        $response = $this->actingAs($manager)->get(route('integrations.google.connect', $connection));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
    }

    public function test_freeagent_connect_redirects_with_a_message_when_not_configured(): void
    {
        config(['services.freeagent.client_id' => null, 'services.freeagent.client_secret' => null]);
        $manager = User::factory()->manager()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'freeagent',
            'name' => 'FreeAgent (workspace)',
            'status' => ConnectionStatus::NotConnected,
        ]);

        $this->actingAs($manager)
            ->get(route('integrations.workspace.freeagent.connect', $workspace))
            ->assertRedirect(route('integrations.workspace.edit', $workspace))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'FREEAGENT_CLIENT_ID'));
    }

    public function test_xero_connect_redirects_with_a_message_when_not_configured(): void
    {
        config(['services.xero.client_id' => null, 'services.xero.client_secret' => null]);
        $manager = User::factory()->manager()->create();
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'xero',
            'name' => 'Xero (workspace)',
            'status' => ConnectionStatus::NotConnected,
        ]);

        $this->actingAs($manager)
            ->get(route('integrations.workspace.xero.connect', $workspace))
            ->assertRedirect(route('integrations.workspace.edit', $workspace))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'XERO_CLIENT_ID'));
    }
}
