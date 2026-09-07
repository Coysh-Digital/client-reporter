<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectionSchedule;
use App\Livewire\Integrations\Setup;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Models\WorkspaceIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionFrequencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_interval_resolves_site_then_workspace_then_global(): void
    {
        config(['client-reporter.collection.default_interval' => 360]);
        $schedule = app(CollectionSchedule::class);

        // No override anywhere → global default.
        $plain = SiteIntegration::factory()->create(['settings' => []]);
        $this->assertSame(360, $schedule->intervalMinutes($plain));

        // Workspace override, no site override → workspace value.
        $workspace = WorkspaceIntegration::query()->create([
            'integration_key' => 'plausible', 'name' => 'Plausible', 'status' => ConnectionStatus::Connected,
            'settings' => ['collection_interval' => 720],
        ]);
        $fromWorkspace = SiteIntegration::factory()->create(['workspace_integration_id' => $workspace->id, 'settings' => []]);
        $this->assertSame(720, $schedule->intervalMinutes($fromWorkspace));

        // Site override wins over both.
        $siteOverride = SiteIntegration::factory()->create([
            'workspace_integration_id' => $workspace->id, 'settings' => ['collection_interval' => 60],
        ]);
        $this->assertSame(60, $schedule->intervalMinutes($siteOverride));

        // No connection → global.
        $this->assertSame(360, $schedule->intervalMinutes());
    }

    public function test_is_due_uses_the_per_connection_interval(): void
    {
        $schedule = app(CollectionSchedule::class);

        $hourly = SiteIntegration::factory()->create([
            'settings' => ['collection_interval' => 60], 'last_attempted_at' => now()->subMinutes(90),
        ]);
        $this->assertTrue($schedule->isDue($hourly), '90 minutes since last, 60-minute interval → due');

        $slow = SiteIntegration::factory()->create([
            'settings' => ['collection_interval' => 120], 'last_attempted_at' => now()->subMinutes(90),
        ]);
        $this->assertFalse($schedule->isDue($slow), '90 minutes since last, 120-minute interval → not due');
    }

    public function test_the_setup_form_saves_a_per_connection_frequency(): void
    {
        Http::fake();
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'plausible'])
            ->set('values.api_token', 'tok')
            ->set('values.site_id', 'example.com')
            ->set('collectionInterval', '720')
            ->call('save')
            ->assertHasNoErrors();

        $connection = SiteIntegration::query()->where('site_id', $site->id)->where('integration_key', 'plausible')->firstOrFail();
        $this->assertSame(720, (int) $connection->setting('collection_interval'));
    }

    public function test_an_invalid_frequency_is_rejected(): void
    {
        Http::fake();
        $manager = User::factory()->manager()->create();
        $site = Site::factory()->create();

        Livewire::actingAs($manager)->test(Setup::class, ['site' => $site, 'key' => 'plausible'])
            ->set('values.api_token', 'tok')
            ->set('values.site_id', 'example.com')
            ->set('collectionInterval', '5')
            ->call('save')
            ->assertHasErrors('collectionInterval');
    }
}
