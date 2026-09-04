<div>
    <x-page-header :title="$user ? 'Edit user' : 'New user'"
                   :subtitle="$user ? $user->email : 'Create an agency staff account.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <div>
            <label for="name" class="cr-label">Name</label>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="cr-label">Email</label>
            <input wire:model="email" id="email" type="email" class="cr-input" required>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="role" class="cr-label">Role</label>
            <select wire:model="role" id="role" class="cr-input">
                @foreach ($this->roles() as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
            @error('role') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="cr-label">{{ $user ? 'New password' : 'Password' }}</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password" class="cr-input">
                @error('password') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                @if ($user) <p class="mt-1 text-xs text-faint">Leave blank to keep current password.</p> @endif
            </div>
            <div>
                <label for="password_confirmation" class="cr-label">Confirm password</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="cr-input">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-muted">
            <input wire:model="is_active" type="checkbox" class="rounded border-line-strong text-accent focus:ring-accent">
            Account is active
        </label>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <button type="submit" class="cr-btn cr-btn-primary">{{ $user ? 'Save changes' : 'Create user' }}</button>
            <a href="{{ route('users.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
