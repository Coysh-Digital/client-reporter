<div class="cr-card px-6 py-7">
    <h1 class="text-lg font-semibold text-ink">Reset your password</h1>
    <p class="mt-1 text-sm text-muted">Enter your email and we'll send a reset link.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <form wire:submit="sendResetLink" class="mt-6 space-y-4">
        <div>
            <label for="email" class="cr-label">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus class="cr-input" required>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="cr-btn cr-btn-primary w-full">Send reset link</button>
    </form>

    <p class="mt-5 text-center text-sm text-muted">
        <a href="{{ route('login') }}" wire:navigate class="text-ink hover:underline">Back to sign in</a>
    </p>
</div>
