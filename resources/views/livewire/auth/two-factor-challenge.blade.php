<div class="cr-card px-6 py-7">
    <h1 class="text-lg font-semibold text-ink">Two-factor authentication</h1>
    <p class="mt-1 text-sm text-muted">
        {{ $recovery
            ? 'Enter one of your recovery codes to sign in.'
            : 'Enter the 6-digit code from your authenticator app.' }}
    </p>

    <form wire:submit="verify" class="mt-6 space-y-4">
        <div>
            <label for="code" class="cr-label">{{ $recovery ? 'Recovery code' : 'Authentication code' }}</label>
            <input wire:model="code" id="code" type="text" autofocus autocomplete="one-time-code"
                   inputmode="{{ $recovery ? 'text' : 'numeric' }}"
                   class="cr-input tracking-widest" required>
            @error('code') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cr-btn cr-btn-primary w-full">
            <span wire:loading.remove wire:target="verify">Verify</span>
            <span wire:loading wire:target="verify">Verifying…</span>
        </button>
    </form>

    <button type="button" wire:click="toggleRecovery" class="mt-4 text-xs text-muted hover:text-ink">
        {{ $recovery ? 'Use an authenticator code instead' : 'Use a recovery code instead' }}
    </button>
</div>
