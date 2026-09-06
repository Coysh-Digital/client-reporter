<div>
    <x-breadcrumbs :items="[['label' => 'Users', 'href' => route('users.index')], ['label' => $user?->name ?? 'New user']]" />
    <x-page-header :title="$user ? 'Edit user' : 'New user'"
                   :subtitle="$user ? $user->email : 'Create an agency staff account.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Name" for="name" required>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
        </x-field>

        <x-field label="Email" for="email" required>
            <input wire:model="email" id="email" type="email" class="cr-input" required>
        </x-field>

        <x-field label="Role" for="role">
            <select wire:model="role" id="role" class="cr-input">
                @foreach ($this->roles() as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field :label="$user ? 'New password' : 'Password'" for="password" :help="$user ? 'Leave blank to keep the current password.' : 'At least 12 characters with letters and numbers.'">
                <input wire:model="password" id="password" type="password" autocomplete="new-password" class="cr-input">
            </x-field>
            <x-field label="Confirm password" for="password_confirmation">
                <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="cr-input">
            </x-field>
        </div>

        <x-checkbox wire:model="is_active" id="is_active" label="Account is active" />

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">{{ $user ? 'Save changes' : 'Create user' }}</x-button>
            <x-button :href="route('users.index')">Cancel</x-button>
        </div>
    </form>
</div>
