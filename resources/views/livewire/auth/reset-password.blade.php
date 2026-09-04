<div class="cr-card px-6 py-7">
    <h1 class="text-lg font-semibold text-ink">Choose a new password</h1>

    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <div>
            <label for="email" class="cr-label">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" class="cr-input" required>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="cr-label">New password</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password" class="cr-input" required>
            @error('password') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="cr-label">Confirm new password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="cr-input" required>
        </div>

        <button type="submit" class="cr-btn cr-btn-primary w-full">Reset password</button>
    </form>
</div>
