<div class="cr-card px-6 py-7">
    <h1 class="text-lg font-semibold text-ink">Sign in</h1>
    <p class="mt-1 text-sm text-muted">Access your agency dashboard.</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label for="email" class="cr-label">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus
                   class="cr-input" required>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="cr-label">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="text-xs text-muted hover:text-ink">Forgot password?</a>
                @endif
            </div>
            <input wire:model="password" id="password" type="password" autocomplete="current-password"
                   class="cr-input" required>
            @error('password') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-muted">
            <input wire:model="remember" type="checkbox" class="rounded border-line-strong text-accent focus:ring-accent">
            Remember me
        </label>

        <button type="submit" class="cr-btn cr-btn-primary w-full">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>
</div>
