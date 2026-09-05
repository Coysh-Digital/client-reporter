<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate();

        // Per-address limit on top of the broker's per-email throttle, so one
        // visitor cannot spray reset emails at every address they can think of.
        if (! RateLimiter::attempt('password-reset|'.request()->ip(), 5, fn () => true)) {
            throw ValidationException::withMessages([
                'email' => 'Too many reset requests. Please wait a minute and try again.',
            ]);
        }

        // Always report success to avoid leaking which emails are registered.
        Password::sendResetLink(['email' => $this->email]);

        session()->flash('status', 'If that email is registered, a reset link is on its way.');
        $this->reset('email');
    }

    public function render(): mixed
    {
        return view('livewire.auth.forgot-password');
    }
}
