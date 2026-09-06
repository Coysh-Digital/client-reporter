<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Choose a password')]
class ResetPassword extends Component
{
    public string $token = '';

    #[Validate('required|string|email')]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** True when the link came from an invitation email (longer-lived token, different wording). */
    public bool $invite = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->string('email');
        $this->invite = request()->boolean('invite');
    }

    public function resetPassword(AuditLogger $audit): mixed
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! RateLimiter::attempt('password-reset|'.request()->ip(), 5, fn () => true)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please wait a minute and try again.',
            ]);
        }

        $status = Password::broker($this->invite ? 'invites' : 'users')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user): void {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                Event::dispatch(new PasswordReset($user));
            }
        );

        $this->reset('password', 'password_confirmation');

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $audit->log($this->invite ? 'auth.invitation.accepted' : 'auth.password.reset', metadata: ['email' => $this->email]);
        session()->flash('status', $this->invite ? 'Your password is set. Please sign in.' : 'Your password has been reset. Please sign in.');

        return $this->redirectRoute('login', navigate: true);
    }

    public function render(): mixed
    {
        return view('livewire.auth.reset-password');
    }
}
