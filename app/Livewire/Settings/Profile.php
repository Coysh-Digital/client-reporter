<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A signed-in user's own account: name, email and password. Every role has
 * this page (client-portal users included); changing the password requires
 * the current one.
 */
#[Title('Your profile')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(AuditLogger $audit): void
    {
        $user = $this->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);
        $audit->log('user.profile_updated', $user);

        $this->dispatch('toast', message: 'Profile saved.', type: 'ok');
    }

    public function changePassword(AuditLogger $audit): void
    {
        $user = $this->user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $current = $this->current_password;
        $new = $this->password;
        $this->reset('current_password', 'password', 'password_confirmation');

        if (! Hash::check($current, (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
        }

        $user->forceFill(['password' => Hash::make($new)])->save();
        $audit->log('user.password_changed', $user);

        $this->dispatch('toast', message: 'Password changed.', type: 'ok');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function render(): mixed
    {
        return view('livewire.settings.profile')->layout(auth()->user()?->isClient() ? 'components.layouts.portal' : 'components.layouts.app');
    }
}
