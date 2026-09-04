<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_staff_user(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Form::class)
            ->set('name', 'New Manager')
            ->set('email', 'manager@acme.test')
            ->set('role', UserRole::Manager->value)
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'manager@acme.test', 'role' => 'manager']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created']);
    }

    public function test_creating_a_user_requires_a_password(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Form::class)
            ->set('name', 'No Password')
            ->set('email', 'nopass@acme.test')
            ->set('role', UserRole::Viewer->value)
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_an_admin_can_edit_a_user_without_changing_the_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->manager()->create(['name' => 'Old Name']);
        $originalHash = $user->password;

        Livewire::actingAs($admin)->test(Form::class, ['user' => $user])
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('delete', $admin->id)
            ->assertHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_an_admin_can_deactivate_another_user(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->manager()->create();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('toggleActive', $user->id);

        $this->assertFalse($user->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deactivated']);
    }

    public function test_an_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->viewer()->create();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('delete', $user->id);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deleted']);
    }
}
