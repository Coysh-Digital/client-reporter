<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Users\Form;
use App\Models\Client;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PortalUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_invite_a_client_portal_user(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create(['name' => 'Agency Admin']);
        $client = Client::factory()->create(['name' => 'Harbour & Vine']);

        Livewire::actingAs($admin)->test(Form::class)
            ->set('name', 'Jo Client')
            ->set('email', 'jo@harbour.test')
            ->set('role', UserRole::Client->value)
            ->set('client_id', $client->id)
            ->set('send_invite', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'jo@harbour.test')->firstOrFail();
        $this->assertTrue($user->isClient());
        $this->assertSame($client->id, $user->client_id);
        $this->assertDatabaseHas('invite_tokens', ['email' => 'jo@harbour.test']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.invited']);

        Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->actionUrl, 'invite=1')
                && str_contains(implode(' ', $mail->introLines), 'Harbour & Vine');
        });
    }

    public function test_a_portal_user_needs_a_client(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)->test(Form::class)
            ->set('name', 'Jo Client')
            ->set('email', 'jo@harbour.test')
            ->set('role', UserRole::Client->value)
            ->call('save')
            ->assertHasErrors('client_id');
    }

    public function test_the_form_preselects_the_client_from_the_client_page_link(): void
    {
        $admin = User::factory()->administrator()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)->get(route('users.create', ['client' => $client->id]))
            ->assertOk()
            ->assertSee('Portal users see only this client');
    }

    public function test_an_invitation_link_sets_the_password_and_a_reset_link_cannot_pose_as_one(): void
    {
        $user = User::factory()->client()->create(['email' => 'jo@harbour.test', 'password' => Hash::make(str_repeat('x', 40))]);
        $token = Password::broker('invites')->getRepository()->create($user);

        Livewire::withQueryParams(['email' => 'jo@harbour.test', 'invite' => 1])
            ->test(ResetPassword::class, ['token' => $token])
            ->assertSet('invite', true)
            ->assertSee('Welcome')
            ->set('password', 'a-chosen-password-1')
            ->set('password_confirmation', 'a-chosen-password-1')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-chosen-password-1', (string) $user->fresh()?->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.invitation.accepted']);

        // An ordinary reset token is not honoured by the invite flow.
        $resetToken = Password::broker('users')->getRepository()->create($user);
        Livewire::withQueryParams(['email' => 'jo@harbour.test', 'invite' => 1])
            ->test(ResetPassword::class, ['token' => $resetToken])
            ->set('password', 'another-password-22')
            ->set('password_confirmation', 'another-password-22')
            ->call('resetPassword')
            ->assertHasErrors('email');
    }

    public function test_signing_in_records_the_last_login_time(): void
    {
        $user = User::factory()->manager()->create(['password' => Hash::make('a-long-enough-password')]);
        $this->assertNull($user->last_login_at);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'a-long-enough-password')
            ->call('login');

        $this->assertNotNull($user->fresh()?->last_login_at);
    }

    public function test_an_invitation_can_be_resent_only_before_the_first_sign_in(): void
    {
        Notification::fake();
        $admin = User::factory()->administrator()->create();
        $client = Client::factory()->create();
        $never = User::factory()->client()->create(['client_id' => $client->id]);
        $signedIn = User::factory()->client()->create(['client_id' => $client->id, 'last_login_at' => now()]);

        Livewire::actingAs($admin)->test(Form::class, ['user' => $never])->call('resendInvite')->assertDispatched('toast');
        Livewire::actingAs($admin)->test(Form::class, ['user' => $signedIn])->call('resendInvite')->assertNotDispatched('toast');

        Notification::assertSentTo($never, UserInvitation::class);
        Notification::assertNotSentTo($signedIn, UserInvitation::class);
    }

    public function test_a_client_user_cannot_be_turned_into_staff_and_the_client_page_lists_portal_users(): void
    {
        $admin = User::factory()->administrator()->create();
        $client = Client::factory()->create(['name' => 'Harbour & Vine']);
        $portal = User::factory()->client()->create(['client_id' => $client->id, 'name' => 'Jo Client']);

        Livewire::actingAs($admin)->test(Form::class, ['user' => $portal])
            ->set('role', UserRole::Manager->value)
            ->call('save')
            ->assertForbidden();

        $this->actingAs($admin)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Portal access')
            ->assertSee('Jo Client')
            ->assertSee('Never signed in')
            ->assertSee(route('users.create', ['client' => $client->id]), false);
    }
}
