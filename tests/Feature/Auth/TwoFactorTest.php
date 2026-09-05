<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Settings\TwoFactor as TwoFactorSettings;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user with 2FA fully enabled and known recovery codes.
     *
     * @param  array<int, string>  $recoveryCodes
     */
    private function userWithTwoFactor(string $secret, array $recoveryCodes = ['AAAAAA-BBBBBB']): User
    {
        return User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make($c), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_login_without_two_factor_signs_in_directly(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_two_factor_diverts_to_the_challenge(): void
    {
        $user = $this->userWithTwoFactor(TwoFactor::generateSecret());

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        // Not logged in yet — only the pending id is held.
        $this->assertGuest();
        $this->assertSame($user->id, session('auth.two_factor.pending_id'));
    }

    public function test_the_challenge_accepts_a_totp_code(): void
    {
        $secret = TwoFactor::generateSecret();
        $user = $this->userWithTwoFactor($secret);
        session(['auth.two_factor.pending_id' => $user->id, 'auth.two_factor.remember' => false]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', TwoFactor::codeAt($secret))
            ->call('verify')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.two_factor.pending_id'));
    }

    public function test_the_challenge_rejects_a_wrong_code(): void
    {
        $user = $this->userWithTwoFactor(TwoFactor::generateSecret());
        session(['auth.two_factor.pending_id' => $user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_signs_in_and_is_consumed(): void
    {
        $user = $this->userWithTwoFactor(TwoFactor::generateSecret(), ['AAAAAA-BBBBBB', 'CCCCCC-DDDDDD']);
        session(['auth.two_factor.pending_id' => $user->id]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('recovery', true)
            ->set('code', 'AAAAAA-BBBBBB')
            ->call('verify')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);

        // The used code is gone; the other remains.
        $remaining = $user->fresh()->two_factor_recovery_codes;
        $this->assertCount(1, $remaining);
        $this->assertTrue(Hash::check('CCCCCC-DDDDDD', $remaining[0]));
        $this->assertFalse(Hash::check('AAAAAA-BBBBBB', $remaining[0]));
    }

    public function test_a_user_can_enable_two_factor_by_confirming_a_code(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(TwoFactorSettings::class)
            ->call('enable');

        $secret = $component->get('secret');
        $this->assertNotEmpty($secret);
        // The setup step renders a scannable QR code.
        $component->assertSee('<svg', false);

        $component->set('confirmCode', TwoFactor::codeAt($secret))
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('enabled', true)
            ->assertCount('recoveryCodes', 8);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_requires_the_password(): void
    {
        $user = $this->userWithTwoFactor(TwoFactor::generateSecret());

        Livewire::actingAs($user)->test(TwoFactorSettings::class)
            ->set('password', 'wrong-password')
            ->call('disable')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        Livewire::actingAs($user)->test(TwoFactorSettings::class)
            ->set('password', 'password')
            ->call('disable')
            ->assertHasNoErrors()
            ->assertSet('enabled', false);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
