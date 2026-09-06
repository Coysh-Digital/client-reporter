<div class="cr-card px-7 py-7">
    {{-- Step indicator --}}
    <ol class="mb-6 flex flex-wrap items-center gap-2 text-xs" aria-label="Installation steps">
        @foreach (['Requirements', 'Database', 'Administrator', 'Agency'] as $i => $label)
            <li class="flex items-center gap-2" @if ($step === $i + 1) aria-current="step" @endif>
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full font-medium',
                    'bg-accent text-white' => $step === $i + 1,
                    'bg-ok-soft text-ok' => $step > $i + 1,
                    'bg-paper text-faint' => $step < $i + 1,
                ]) aria-hidden="true">{{ $step > $i + 1 ? '✓' : $i + 1 }}</span>
                <span class="{{ $step === $i + 1 ? 'text-ink' : 'text-faint' }}">{{ $label }}</span>
                @if (! $loop->last) <span class="text-line-strong" aria-hidden="true">—</span> @endif
            </li>
        @endforeach
    </ol>

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
            <x-alert variant="danger" class="mt-4">Please resolve the required items before continuing.</x-alert>
        @endunless
        <div class="mt-6 flex justify-end">
            <x-button variant="primary" wire:click="next" :disabled="! $this->requirementsMet()">Continue</x-button>
        </div>
    @endif

    {{-- Step 2: Database --}}
    @if ($step === 2)
        <h1 class="text-lg font-semibold text-ink">Database</h1>
        <p class="mt-1 text-sm text-muted">SQLite needs no setup and is perfect for smaller installs.</p>
        <div class="mt-4 space-y-4">
            <x-field label="Database type" for="db_connection">
                <select wire:model.live="db_connection" id="db_connection" class="cr-input">
                    <option value="sqlite">SQLite (recommended for shared hosting)</option>
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="pgsql">PostgreSQL</option>
                </select>
            </x-field>
            @if ($db_connection !== 'sqlite')
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Host" for="db_host"><input wire:model="db_host" id="db_host" class="cr-input"></x-field>
                    <x-field label="Port" for="db_port"><input wire:model="db_port" id="db_port" inputmode="numeric" class="cr-input"></x-field>
                    <x-field label="Database" for="db_database"><input wire:model="db_database" id="db_database" class="cr-input"></x-field>
                    <x-field label="Username" for="db_username"><input wire:model="db_username" id="db_username" class="cr-input"></x-field>
                    <x-field label="Password" for="db_password" class="sm:col-span-2"><input wire:model="db_password" id="db_password" type="password" autocomplete="off" class="cr-input"></x-field>
                </div>
                <x-button wire:click="testDatabase">Test connection</x-button>
                @if ($dbTestResult === 'ok')
                    <x-alert variant="ok">Connected successfully.</x-alert>
                @elseif ($dbTestResult)
                    <x-alert variant="danger">{{ $dbTestResult }}</x-alert>
                @endif
            @endif
        </div>
        <div class="mt-6 flex justify-between">
            <x-button wire:click="back">Back</x-button>
            <x-button variant="primary" wire:click="next">Continue</x-button>
        </div>
    @endif

    {{-- Step 3: Administrator --}}
    @if ($step === 3)
        <h1 class="text-lg font-semibold text-ink">Create your administrator</h1>
        <div class="mt-4 space-y-4">
            <x-field label="Name" for="admin_name" required><input wire:model="admin_name" id="admin_name" autocomplete="name" class="cr-input"></x-field>
            <x-field label="Email" for="admin_email" required><input wire:model="admin_email" id="admin_email" type="email" autocomplete="email" class="cr-input"></x-field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Password" for="admin_password" required help="At least 12 characters with letters and numbers."><input wire:model="admin_password" id="admin_password" type="password" autocomplete="new-password" class="cr-input"></x-field>
                <x-field label="Confirm password" for="admin_password_confirmation" required><input wire:model="admin_password_confirmation" id="admin_password_confirmation" type="password" autocomplete="new-password" class="cr-input"></x-field>
            </div>
        </div>
        <div class="mt-6 flex justify-between">
            <x-button wire:click="back">Back</x-button>
            <x-button variant="primary" wire:click="next">Continue</x-button>
        </div>
    @endif

    {{-- Step 4: Agency + finish --}}
    @if ($step === 4)
        <h1 class="text-lg font-semibold text-ink">Your agency</h1>
        <p class="mt-1 text-sm text-muted">This is the default branding for client-facing reports. You can refine it later.</p>
        <div class="mt-4 space-y-4">
            <x-field label="Agency name" for="agency_name" required><input wire:model="agency_name" id="agency_name" class="cr-input"></x-field>
            <x-field label="Application URL" for="app_url" required help="The address people use to open Client Reporter, including https://."><input wire:model="app_url" id="app_url" type="url" class="cr-input"></x-field>
            <x-field label="Brand colour" for="primary_color">
                <div class="flex items-center gap-2">
                    <input wire:model="primary_color" id="primary_color-swatch" type="color" aria-label="Brand colour picker" class="h-9 w-12 rounded border border-line-strong">
                    <input wire:model="primary_color" id="primary_color" type="text" class="cr-input max-w-[140px]">
                </div>
            </x-field>
        </div>

        @if ($envNotWritable)
            <x-alert variant="warn" title="Your .env file isn't writable." class="mt-4">
                <p>Add these lines to your <code>.env</code>, then run the install again:</p>
                <pre class="mt-2 overflow-x-auto rounded bg-white/60 p-2 text-xs text-ink">{{ $envNotWritable }}</pre>
            </x-alert>
        @endif

        <div class="mt-6 flex justify-between">
            <x-button wire:click="back">Back</x-button>
            <x-button variant="primary" wire:click="install" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="install">Install Client Reporter</span>
                <span wire:loading wire:target="install">Installing…</span>
            </x-button>
        </div>
    @endif
</div>
