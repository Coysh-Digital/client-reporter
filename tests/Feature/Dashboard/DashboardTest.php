<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\ConnectionStatus;
use App\Livewire\Dashboard;
use App\Models\Client;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_for_an_admin(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Tim']);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Tim');
    }

    public function test_a_failed_integration_appears_in_needs_attention(): void
    {
        $admin = User::factory()->administrator()->create();
        $client = Client::factory()->create(['name' => 'Harbour & Vine']);
        $site = Site::factory()->for($client)->create(['is_active' => true, 'name' => 'Main site']);
        SiteIntegration::factory()->for($site)->create(['status' => ConnectionStatus::Error]);

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertSee('Needs attention')
            ->assertSee('Harbour & Vine');
    }

    public function test_the_period_toggle_updates_state(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Dashboard::class)
            ->assertSet('period', 'this_month')
            ->call('setPeriod', 'last_30_days')
            ->assertSet('period', 'last_30_days');
    }
}
