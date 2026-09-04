<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Integrations\IntegrationRegistry;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_connect_screen_shows_step_by_step_guidance(): void
    {
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        $this->actingAs($manager)
            ->get(route('sites.integrations.connect', ['site' => $site, 'key' => 'matomo']))
            ->assertOk()
            ->assertSee('How to connect Matomo')
            ->assertSee('Auth tokens', escape: false);
    }

    public function test_every_bundled_integration_has_setup_steps(): void
    {
        $registry = app(IntegrationRegistry::class);

        foreach ($registry->all() as $integration) {
            // Workspace-only integrations (billing) have no per-site connect
            // screen at all, so their guidance lives in workspaceSetupSteps().
            $steps = $integration->onlyWorkspaceScope()
                ? $integration->workspaceSetupSteps()
                : $integration->setupSteps();

            $this->assertNotEmpty($steps, $integration->key().' should provide setup steps');
        }
    }
}
