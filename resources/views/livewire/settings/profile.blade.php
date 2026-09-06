<div>
    <x-page-header title="Your profile" subtitle="Your name, sign-in email and password." eyebrow="Account" />

    <div class="mx-auto max-w-3xl space-y-6">
        <form wire:submit="save" class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Details</h2></div>
            <div class="space-y-4 px-5 py-5">
                <x-field label="Name" for="profile-name" name="name">
                    <input wire:model="name" id="profile-name" type="text" class="cr-input" required autocomplete="name">
                </x-field>
                <x-field label="Email" for="profile-email" name="email" help="Used to sign in and for password resets.">
                    <input wire:model="email" id="profile-email" type="email" class="cr-input" required autocomplete="email">
                </x-field>
                <div class="flex items-center gap-3 border-t border-line pt-4">
                    <x-button variant="primary" type="submit">
                        <span wire:loading.remove wire:target="save">Save changes</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-button>
                </div>
            </div>
        </form>

        <form wire:submit="changePassword" class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Password</h2></div>
            <div class="space-y-4 px-5 py-5">
                <x-field label="Current password" for="profile-current-password" name="current_password">
                    <input wire:model="current_password" id="profile-current-password" type="password" class="cr-input" required autocomplete="current-password">
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="New password" for="profile-password" name="password" help="At least 12 characters.">
                        <input wire:model="password" id="profile-password" type="password" class="cr-input" required autocomplete="new-password">
                    </x-field>
                    <x-field label="Confirm new password" for="profile-password-confirmation" name="password_confirmation">
                        <input wire:model="password_confirmation" id="profile-password-confirmation" type="password" class="cr-input" required autocomplete="new-password">
                    </x-field>
                </div>
                <div class="flex items-center gap-3 border-t border-line pt-4">
                    <x-button variant="secondary" type="submit">
                        <span wire:loading.remove wire:target="changePassword">Change password</span>
                        <span wire:loading wire:target="changePassword">Changing…</span>
                    </x-button>
                    <a href="{{ route('settings.two-factor') }}" wire:navigate class="text-sm text-muted hover:text-ink">Two-factor authentication →</a>
                </div>
            </div>
        </form>
    </div>
</div>
