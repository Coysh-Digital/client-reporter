<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(AuditLogger $audit): mixed
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        // Verify the credentials without logging in yet, so a 2FA-protected
        // account can be diverted to its second-factor challenge first.
        $credentials = ['email' => $this->email, 'password' => $this->password];
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($this->throttleKey());
            $audit->log('auth.login.failed', metadata: ['email' => $this->email]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        // A deactivated account may still have valid credentials; refuse it.
        if (! $user->is_active) {
            $audit->log('auth.login.blocked', $user, metadata: ['reason' => 'inactive']);

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Second factor required: hand off to the challenge, holding only the
        // pending user id (never the password) and the remember choice.
        if ($user->hasTwoFactorEnabled()) {
            session()->put('auth.two_factor.pending_id', $user->id);
            session()->put('auth.two_factor.remember', $this->remember);

            return $this->redirectRoute('two-factor.challenge', navigate: true);
        }

        Auth::login($user, $this->remember);
        session()->regenerate();

        $audit->log('auth.login.success', $user);

        $default = $user->isClient()
            ? route('portal.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return $this->redirectIntended(default: $default, navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        Event::dispatch(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render(): mixed
    {
        return view('livewire.auth.login');
    }
}
