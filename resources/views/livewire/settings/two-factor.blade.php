<div>
    <x-page-header title="Two-factor authentication" subtitle="Protect your account with a second step at sign-in." eyebrow="Security" />

    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    {{-- Freshly issued recovery codes: shown once. --}}
    @if (! empty($recoveryCodes))
        <div class="mb-6 cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Recovery codes</h2></div>
            <div class="px-5 py-4">
                <p class="text-sm text-muted">Store these somewhere safe. Each code can be used once to sign in if you lose your authenticator. They won’t be shown again.</p>
                <div class="mt-3 grid max-w-md grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($recoveryCodes as $code)
                        <div class="rounded-md bg-paper px-3 py-1.5 text-ink">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="cr-panel">
        <div class="border-b border-line px-5 py-3.5">
            <h2 class="cr-eyebrow">Authenticator app</h2>
        </div>
        <div class="px-5 py-5">
            @if ($secret !== '')
                {{-- Setup in progress --}}
                <p class="text-sm text-muted">Scan this QR code with your authenticator app (Google Authenticator, 1Password, Authy…), then enter the 6-digit code it shows to confirm. Can’t scan? Use the setup key below.</p>

                <div class="mt-4 inline-block rounded-lg border border-line bg-white p-3">
                    {!! $this->qrSvg() !!}
                </div>

                <div class="mt-4 max-w-md">
                    <label class="cr-label">Setup key</label>
                    <input type="text" readonly value="{{ $secret }}" onfocus="this.select()"
                           class="cr-input font-mono tracking-widest">
                    <p class="mt-1 text-[11px] text-faint">Enter this manually if you can’t scan the code.</p>
                </div>

                <form wire:submit="confirm" class="mt-4 max-w-md space-y-3">
                    <div>
                        <label for="confirmCode" class="cr-label">Verification code</label>
                        <input wire:model="confirmCode" id="confirmCode" type="text" inputmode="numeric"
                               autocomplete="one-time-code" class="cr-input tracking-widest" required>
                        @error('confirmCode') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="cr-btn cr-btn-primary">Confirm &amp; enable</button>
                        <button type="button" wire:click="cancelSetup" class="text-sm text-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            @elseif ($enabled)
                <div class="flex items-center gap-2">
                    <x-status-dot variant="ok" label="Enabled" />
                </div>
                <p class="mt-2 text-sm text-muted">Two-factor authentication is on. You’ll be asked for a code from your authenticator app each time you sign in.</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button wire:click="regenerateRecoveryCodes" class="cr-btn cr-btn-secondary">Regenerate recovery codes</button>
                </div>

                <form wire:submit="disable" class="mt-6 max-w-md border-t border-line pt-5">
                    <p class="cr-eyebrow mb-2">Turn off</p>
                    <label for="password" class="cr-label">Confirm your password to disable</label>
                    <div class="flex items-start gap-2">
                        <div class="flex-1">
                            <input wire:model="password" id="password" type="password" autocomplete="current-password" class="cr-input">
                            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="cr-btn cr-btn-secondary shrink-0 text-danger">Disable</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-muted">Add a second step at sign-in using an authenticator app (Google Authenticator, 1Password, Authy…). We’ll also give you one-time recovery codes.</p>
                <button wire:click="enable" class="cr-btn cr-btn-primary mt-4">Enable two-factor authentication</button>
            @endif
        </div>
    </div>
</div>
