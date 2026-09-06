<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Support\AuditLogger;
use App\Support\Branding\BrandingResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create or edit an account: agency staff (administrator, manager, viewer) or a
 * client portal user tied to one client. A new account can be given a password
 * here or sent an invitation to choose its own.
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'manager';

    public ?int $client_id = null;

    public bool $is_active = true;

    /** Create only: email a set-password link instead of typing a password. */
    public bool $send_invite = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?User $user = null): void
    {
        $this->authorize('manage-users');

        if ($user?->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->client_id = $user->client_id;
            $this->is_active = $user->is_active;
            $this->send_invite = false;

            return;
        }

        // Arriving from a client page: start as a portal user for that client.
        $client = request()->integer('client');
        if ($client > 0 && Client::query()->whereKey($client)->exists()) {
            $this->role = UserRole::Client->value;
            $this->client_id = $client;
        }
    }

    public function updatedRole(): void
    {
        if ($this->role !== UserRole::Client->value) {
            $this->client_id = null;
        }
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-users');

        // Accounts never cross the staff/client line: a portal user keeps its
        // client and a staff account cannot be demoted into someone's portal.
        if ($this->user !== null && $this->user->isClient() !== ($this->role === UserRole::Client->value)) {
            abort(403);
        }

        $isClient = $this->role === UserRole::Client->value;
        $inviting = $this->user === null && $this->send_invite;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'client_id' => [$isClient ? 'required' : 'nullable', 'integer', Rule::exists('clients', 'id')],
            'is_active' => ['boolean'],
            'password' => [$this->user !== null || $inviting ? 'nullable' : 'required', 'string', PasswordRule::defaults(), 'confirmed'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'client_id' => $isClient ? (int) $validated['client_id'] : null,
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        if ($this->user !== null) {
            $this->user->update($attributes);
            $audit->log('user.updated', $this->user);
            $message = 'User updated.';
        } else {
            // An invited account gets an unguessable placeholder until the
            // person chooses their own password from the emailed link.
            $attributes['password'] ??= Hash::make(Str::random(40));
            $user = User::query()->create($attributes);
            $audit->log('user.created', $user, metadata: ['role' => $user->role->value, 'invited' => $inviting]);

            if ($inviting) {
                $this->sendInvitation($user);
                $audit->log('user.invited', $user);
            }

            $message = $inviting ? 'User created and invitation sent.' : 'User created.';
        }

        $this->reset('password', 'password_confirmation');
        session()->flash('status', $message);

        return $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * Send a fresh invitation to an existing account that has never signed in.
     */
    public function resendInvite(AuditLogger $audit): void
    {
        $this->authorize('manage-users');

        if ($this->user === null || $this->user->last_login_at !== null) {
            return;
        }

        $this->sendInvitation($this->user);
        $audit->log('user.invited', $this->user);

        $this->dispatch('toast', message: 'Invitation sent to '.$this->user->email.'.', type: 'ok');
    }

    private function sendInvitation(User $user): void
    {
        $token = Password::broker('invites')->getRepository()->create($user);
        $agency = app(BrandingResolver::class)->global()->agency_name ?: config('client-reporter.name', 'Client Reporter');
        $clientName = $user->isClient() ? Client::query()->whereKey($user->client_id)->value('name') : null;

        $user->notify(new UserInvitation(
            token: $token,
            invitedBy: (string) auth()->user()?->name,
            agencyName: (string) $agency,
            clientName: is_string($clientName) ? $clientName : null,
        ));
    }

    /**
     * @return array<int, UserRole>
     */
    public function roles(): array
    {
        return UserRole::cases();
    }

    /**
     * @return Collection<int, Client>
     */
    public function clients(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(): mixed
    {
        return view('livewire.users.form')->title($this->user ? 'Edit user' : 'New user');
    }
}
