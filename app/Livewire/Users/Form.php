<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'manager';

    public bool $is_active = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->is_active = $user->is_active;
        }
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-users');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'role' => ['required', Rule::in(array_map(fn (UserRole $r) => $r->value, UserRole::staffRoles()))],
            'is_active' => ['boolean'],
            'password' => [$this->user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        if ($this->user) {
            $this->user->update($attributes);
            $audit->log('user.updated', $this->user);
        } else {
            $user = User::query()->create($attributes);
            $audit->log('user.created', $user);
        }

        session()->flash('status', $this->user ? 'User updated.' : 'User created.');

        return $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * @return array<int, UserRole>
     */
    public function roles(): array
    {
        return UserRole::staffRoles();
    }

    public function render(): mixed
    {
        return view('livewire.users.form');
    }
}
