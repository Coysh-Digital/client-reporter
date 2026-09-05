<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Billing\InvoicePanel;
use App\Livewire\Branding\Manage;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Reports\Builder;
use App\Livewire\Reports\SharePanel;
use App\Models\Client;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_passes_every_gate(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-clients'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($admin)->allows('access-admin'));
    }

    public function test_manager_can_manage_working_data_but_not_admin_areas(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue(Gate::forUser($manager)->allows('manage-clients'));
        $this->assertTrue(Gate::forUser($manager)->allows('manage-reports'));
        $this->assertFalse(Gate::forUser($manager)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($manager)->allows('manage-settings'));
    }

    public function test_viewer_can_only_access_admin_but_not_manage(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue(Gate::forUser($viewer)->allows('access-admin'));
        $this->assertFalse(Gate::forUser($viewer)->allows('manage-clients'));
        $this->assertFalse(Gate::forUser($viewer)->allows('manage-users'));
    }

    public function test_client_user_cannot_access_admin(): void
    {
        $client = User::factory()->client()->create();

        $this->assertFalse(Gate::forUser($client)->allows('access-admin'));
        $this->assertFalse($client->isStaff());
    }

    public function test_role_hierarchy_is_respected(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertTrue($manager->hasAtLeastRole(UserRole::Viewer));
        $this->assertTrue($manager->hasAtLeastRole(UserRole::Manager));
        $this->assertFalse($manager->hasAtLeastRole(UserRole::Administrator));
    }

    public function test_non_admin_is_forbidden_from_the_users_page(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/users')->assertForbidden();
    }

    public function test_admin_can_reach_the_users_page(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/users')->assertOk();
    }

    /**
     * Livewire actions are reachable without visiting the page that renders
     * them, so every mutating action re-checks the gate itself.
     */
    public function test_a_viewer_cannot_invoke_mutating_livewire_actions(): void
    {
        $viewer = User::factory()->viewer()->create();
        $client = Client::factory()->create();
        $report = Report::factory()->create();

        Livewire::actingAs($viewer)->test(SharePanel::class, ['report' => $report])
            ->call('createLink')->assertForbidden();

        Livewire::actingAs($viewer)->test(Builder::class, ['report' => $report])
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(InvoicePanel::class, ['client' => $client])
            ->set('number', 'INV-1')->set('amount', '10')
            ->call('save')->assertForbidden();

        Livewire::actingAs($viewer)->test(ClientsIndex::class)
            ->call('delete', $client->id)->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_a_manager_cannot_reach_global_branding_through_the_component(): void
    {
        $manager = User::factory()->manager()->create();

        Livewire::actingAs($manager)->test(Manage::class)->assertForbidden();
    }
}
