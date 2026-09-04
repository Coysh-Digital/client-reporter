<div class="cr-card px-7 py-7">
    {{-- Step indicator --}}
    <div class="mb-6 flex items-center gap-2 text-xs">
        @foreach (['Requirements', 'Database', 'Administrator', 'Agency'] as $i => $label)
            <div class="flex items-center gap-2">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full font-medium',
                    'bg-accent text-white' => $step === $i + 1,
                    'bg-ok-soft text-ok' => $step > $i + 1,
                    'bg-paper text-faint' => $step < $i + 1,
                ])>{{ $step > $i + 1 ? '✓' : $i + 1 }}</span>
                <span class="{{ $step === $i + 1 ? 'text-ink' : 'text-faint' }}">{{ $label }}</span>
                @if (! $loop->last) <span class="text-line-strong">—</span> @endif
            </div>
        @endforeach
    </div>

    {{-- Step 1: Requirements --}}
    @if ($step === 1)
        <h1 class="text-lg font-semibold text-ink">Server requirements</h1>
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($this->requirements() as $check)
                <li class="flex items-center justify-between">
                    <span class="text-ink">{{ $check['label'] }}</span>
                    @if ($check['ok'])
                        <x-badge variant="ok">OK</x-badge>
                    @elseif ($check['required'])
                        <x-badge variant="danger">Required</x-badge>
                    @else
                        <x-badge variant="warn">Recommended</x-badge>
                    @endif
                </li>
            @endforeach
        </ul>
        @unless ($this->requirementsMet())
            <p class="mt-4 rounded bg-danger-soft px-3 py-2 text-sm text-danger">Please resolve the required items before continuing.</p>
        @endunless
        <div class="mt-6 flex justify-end">
            <button wire:click="next" @disabled(! $this->requirementsMet()) class="cr-btn cr-btn-primary">Continue</button>
        </div>
    @endif

    {{-- Step 2: Database --}}
    @if ($step === 2)
        <h1 class="text-lg font-semibold text-ink">Database</h1>
        <p class="mt-1 text-sm text-muted">SQLite needs no setup and is perfect for smaller installs.</p>
        <div class="mt-4 space-y-4">
            <div>
                <label class="cr-label">Database type</label>
                <select wire:model.live="db_connection" class="cr-input">
                    <option value="sqlite">SQLite (recommended for shared hosting)</option>
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="pgsql">PostgreSQL</option>
                </select>
            </div>
            @if ($db_connection !== 'sqlite')
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="cr-label">Host</label><input wire:model="db_host" class="cr-input"></div>
                    <div><label class="cr-label">Port</label><input wire:model="db_port" class="cr-input"></div>
                    <div><label class="cr-label">Database</label><input wire:model="db_database" class="cr-input"></div>
                    <div><label class="cr-label">Username</label><input wire:model="db_username" class="cr-input"></div>
                    <div class="sm:col-span-2"><label class="cr-label">Password</label><input wire:model="db_password" type="password" class="cr-input"></div>
                </div>
                <button wire:click="testDatabase" class="cr-btn cr-btn-secondary">Test connection</button>
                @if ($dbTestResult === 'ok')
                    <p class="rounded bg-ok-soft px-3 py-2 text-sm text-ok">Connected successfully.</p>
                @elseif ($dbTestResult)
                    <p class="rounded bg-danger-soft px-3 py-2 text-sm text-danger">{{ $dbTestResult }}</p>
                @endif
            @endif
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="back" class="cr-btn cr-btn-secondary">Back</button>
            <button wire:click="next" class="cr-btn cr-btn-primary">Continue</button>
        </div>
    @endif

    {{-- Step 3: Administrator --}}
    @if ($step === 3)
        <h1 class="text-lg font-semibold text-ink">Create your administrator</h1>
        <div class="mt-4 space-y-4">
            <div><label class="cr-label">Name</label><input wire:model="admin_name" class="cr-input">@error('admin_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div><label class="cr-label">Email</label><input wire:model="admin_email" type="email" class="cr-input">@error('admin_email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="cr-label">Password</label><input wire:model="admin_password" type="password" class="cr-input">@error('admin_password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>
                <div><label class="cr-label">Confirm password</label><input wire:model="admin_password_confirmation" type="password" class="cr-input"></div>
            </div>
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="back" class="cr-btn cr-btn-secondary">Back</button>
            <button wire:click="next" class="cr-btn cr-btn-primary">Continue</button>
        </div>
    @endif

    {{-- Step 4: Agency + finish --}}
    @if ($step === 4)
        <h1 class="text-lg font-semibold text-ink">Your agency</h1>
        <p class="mt-1 text-sm text-muted">This is the default branding for client-facing reports. You can refine it later.</p>
        <div class="mt-4 space-y-4">
            <div><label class="cr-label">Agency name</label><input wire:model="agency_name" class="cr-input">@error('agency_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div><label class="cr-label">Application URL</label><input wire:model="app_url" class="cr-input">@error('app_url')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div>
                <label class="cr-label">Brand colour</label>
                <div class="flex items-center gap-2">
                    <input wire:model="primary_color" type="color" class="h-9 w-12 rounded border border-line-strong">
                    <input wire:model="primary_color" type="text" class="cr-input max-w-[140px]">
                </div>
                @error('primary_color')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($envNotWritable)
            <div class="mt-4 rounded bg-warn-soft px-3 py-3 text-sm text-warn">
                <p class="font-medium">Your .env file isn't writable.</p>
                <p class="mt-1">Add these lines to your <code>.env</code>, then run the install again:</p>
                <pre class="mt-2 overflow-x-auto rounded bg-white/60 p-2 text-xs text-ink">{{ $envNotWritable }}</pre>
            </div>
        @endif

        <div class="mt-6 flex justify-between">
            <button wire:click="back" class="cr-btn cr-btn-secondary">Back</button>
            <button wire:click="install" wire:loading.attr="disabled" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="install">Install Client Reporter</span>
                <span wire:loading wire:target="install">Installing…</span>
            </button>
        </div>
    @endif
</div>
