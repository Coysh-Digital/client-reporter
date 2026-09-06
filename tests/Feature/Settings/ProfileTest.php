<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_open_their_profile(): void
    {
        foreach (['administrator', 'manager', 'viewer', 'client'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get('/settings/profile')->assertOk()->assertSee('Your profile');
        }
    }

    public function test_a_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->viewer()->create(['email' => 'old@example.test']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'New Name')
            ->set('email', 'new@example.test')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.test']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.profile_updated']);
    }

    public function test_an_email_already_used_by_someone_else_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->viewer()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('email', 'taken@example.test')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::factory()->viewer()->create(['password' => Hash::make('original-password-1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'wrong-password-99')
            ->set('password', 'a-brand-new-password-1')
            ->set('password_confirmation', 'a-brand-new-password-1')
            ->call('changePassword')
            ->assertHasErrors('current_password')
            ->assertSet('password', '')
            ->assertSet('current_password', '');

        $this->assertTrue(Hash::check('original-password-1', (string) $user->fresh()?->password));
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = User::factory()->viewer()->create(['password' => Hash::make('original-password-1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'original-password-1')
            ->set('password', 'a-brand-new-password-1')
            ->set('password_confirmation', 'a-brand-new-password-1')
            ->call('changePassword')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertTrue(Hash::check('a-brand-new-password-1', (string) $user->fresh()?->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_changed']);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $user = User::factory()->viewer()->create(['password' => Hash::make('original-password-1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'original-password-1')
            ->set('password', 'short1')
            ->set('password_confirmation', 'short1')
            ->call('changePassword')
            ->assertHasErrors('password');
    }
}
