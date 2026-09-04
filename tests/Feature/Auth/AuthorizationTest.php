<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
}
