<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

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

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            $audit->log('auth.login.failed', metadata: ['email' => $this->email]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        // A deactivated account may still have valid credentials; refuse it.
        if ($user !== null && ! $user->is_active) {
            Auth::logout();
            $audit->log('auth.login.blocked', $user, metadata: ['reason' => 'inactive']);

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $audit->log('auth.login.success', $user);

        $default = $user !== null && $user->isClient()
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
