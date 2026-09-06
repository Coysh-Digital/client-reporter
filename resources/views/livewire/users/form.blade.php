<div>
    <x-breadcrumbs :items="[['label' => 'Users', 'href' => route('users.index')], ['label' => $user?->name ?? 'New user']]" />
    <x-page-header :title="$user ? 'Edit user' : 'New user'"
                   :subtitle="$user ? $user->email : 'Give a colleague access, or let a client into their portal.'" />

    @php $isClient = $role === \App\Enums\UserRole::Client->value; @endphp

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Name" for="name" required>
            <input wire:model="name" id="name" type="text" autocomplete="off" class="cr-input" required>
        </x-field>

        <x-field label="Email" for="email" required :help="$user === null && $send_invite ? 'The invitation goes here.' : null">
            <input wire:model="email" id="email" type="email" autocomplete="off" class="cr-input" required>
        </x-field>

        <x-field label="Role" for="role" :help="\App\Enums\UserRole::tryFrom($role)?->description()">
            <select wire:model.live="role" id="role" class="cr-input" @disabled($user !== null)>
                @foreach ($this->roles() as $roleOption)
                    @if ($user === null || $user->isClient() === ($roleOption === \App\Enums\UserRole::Client))
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endif
                @endforeach
            </select>
        </x-field>

        @if ($isClient)
            <x-field label="Client" for="client_id" required help="Portal users see only this client's sites and generated reports.">
                <select wire:model="client_id" id="client_id" class="cr-input">
                    <option value="">Select a client…</option>
                    @foreach ($this->clients() as $clientOption)
                        <option value="{{ $clientOption->id }}">{{ $clientOption->name }}</option>
                    @endforeach
                </select>
            </x-field>
        @endif

        @if ($user === null)
            <x-toggle wire:model.live="send_invite" label="Send an invitation email" help="They choose their own password from a link that works for three days. Turn this off to set a password now." />
        @endif

        @if ($user !== null || ! $send_invite)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field :label="$user ? 'New password' : 'Password'" for="password" :help="$user ? 'Leave blank to keep the current password.' : 'At least 12 characters with letters and numbers.'">
                    <input wire:model="password" id="password" type="password" autocomplete="new-password" class="cr-input">
                </x-field>
                <x-field label="Confirm password" for="password_confirmation">
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="cr-input">
                </x-field>
            </div>
        @endif

        <x-checkbox wire:model="is_active" id="is_active" label="Account is active" help="Inactive accounts cannot sign in and lose any API access." />

        @if ($user !== null && $user->last_login_at === null)
            <x-alert variant="info">
                {{ $user->name }} has never signed in.
                <x-slot:action><x-button size="sm" wire:click="resendInvite" icon="envelope">Send invitation</x-button></x-slot:action>
            </x-alert>
        @endif

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">{{ $user ? 'Save changes' : ($send_invite ? 'Create & send invitation' : 'Create user') }}</x-button>
            <x-button :href="route('users.index')">Cancel</x-button>
        </div>
    </form>
</div>
