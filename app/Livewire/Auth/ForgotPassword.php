<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
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
