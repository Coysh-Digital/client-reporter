<div class="cr-card px-6 py-7">
    <h1 class="text-lg font-semibold text-ink">{{ $invite ? 'Welcome — choose a password' : 'Choose a new password' }}</h1>
    @if ($invite)
        <p class="mt-1 text-sm text-muted">Set a password for {{ $email }} to finish setting up your account.</p>
    @endif

    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <x-field label="Email" for="email">
            <input wire:model="email" id="email" type="email" autocomplete="username" class="cr-input" required>
        </x-field>

        <x-field :label="$invite ? 'Password' : 'New password'" for="password" help="At least 12 characters with letters and numbers.">
            <input wire:model="password" id="password" type="password" autocomplete="new-password" class="cr-input" required>
        </x-field>

        <x-field :label="$invite ? 'Confirm password' : 'Confirm new password'" for="password_confirmation">
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="cr-input" required>
        </x-field>

        <x-button type="submit" variant="primary" class="w-full">{{ $invite ? 'Set password & continue' : 'Reset password' }}</x-button>
    </form>
</div>
