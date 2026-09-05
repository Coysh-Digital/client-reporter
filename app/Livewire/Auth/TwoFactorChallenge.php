<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactor;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The second step of login for a 2FA-protected account: verify a TOTP code from
 * the authenticator app, or a one-time recovery code. The user is not logged in
 * until this passes; only their pending id and remember choice are held in the
 * session (never the password).
 */
#[Layout('components.layouts.guest')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $recovery = false;

    public function mount(): void
    {
        if (! session()->has('auth.two_factor.pending_id')) {
            $this->redirectRoute('login', navigate: true);
        }
    }

    /**
     * Switch between the authenticator-code and recovery-code inputs.
     */
    public function toggleRecovery(): void
    {
        $this->recovery = ! $this->recovery;
        $this->code = '';
        $this->resetErrorBag();
    }

    public function verify(AuditLogger $audit): mixed
    {
        $this->ensureIsNotRateLimited();

        $user = $this->pendingUser();
        if ($user === null) {
            return $this->redirectRoute('login', navigate: true);
        }

        $passed = $this->recovery
            ? $this->consumeRecoveryCode($user)
            : TwoFactor::verify((string) $user->two_factor_secret, $this->code);

        if (! $passed) {
            RateLimiter::hit($this->throttleKey());
            $audit->log('auth.two_factor.failed', $user, metadata: ['recovery' => $this->recovery]);

            throw ValidationException::withMessages([
                'code' => $this->recovery
                    ? 'That recovery code is invalid or already used.'
                    : 'That code is invalid or has expired.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $remember = (bool) session()->pull('auth.two_factor.remember', false);
        session()->forget('auth.two_factor.pending_id');

        Auth::login($user, $remember);
        session()->regenerate();

        $audit->log('auth.login.success', $user, metadata: ['two_factor' => true]);

        $default = $user->isClient()
            ? route('portal.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return $this->redirectIntended(default: $default, navigate: true);
    }

    /**
     * Check the code against the stored recovery codes and, on a match, remove
     * the used one so it cannot be replayed.
     */
    private function consumeRecoveryCode(User $user): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $input = trim($this->code);

        foreach ($codes as $index => $hashed) {
            if (Hash::check($input, $hashed)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    private function pendingUser(): ?User
    {
        $id = session('auth.two_factor.pending_id');

        return $id !== null ? User::query()->whereKey($id)->first() : null;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        Event::dispatch(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate('2fa|'.session('auth.two_factor.pending_id', '').'|'.request()->ip());
    }

    public function render(): mixed
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
