<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_a_user_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->administrator()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.success', 'user_id' => $user->id]);
    }

    public function test_invalid_credentials_are_rejected_and_audited(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.failed']);
    }

    public function test_a_deactivated_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.blocked']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create();

        $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'nope');

        for ($i = 0; $i < 5; $i++) {
            $component->call('login');
        }

        $component->call('login')->assertHasErrors('email');
        $this->assertStringContainsString('seconds', collect($component->errors()->get('email'))->implode(' '));
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_deactivated_user_is_logged_out_by_middleware(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }
}
