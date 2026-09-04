<div>
    @php $manifest = $integration->manifest(); @endphp

    <div class="mb-2 text-sm text-muted">
        <a href="{{ route('sites.show', $site) }}" wire:navigate class="hover:text-ink">{{ $site->name }}</a>
        <span class="text-faint">/</span> Connect a service
    </div>

    <x-page-header :title="($connection ? 'Manage ' : 'Connect ') . $manifest->name"
                   :subtitle="$manifest->description" />

    @error('verification')
        <div class="mb-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">{{ $message }}</div>
    @enderror

    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    @if ($needsOAuthConnect && $connection)
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connect your account</h3>
            <p class="mt-1 text-sm text-muted">Authorise access so Client Reporter can read this property's analytics.</p>
            <a href="{{ route('integrations.google.connect', $connection) }}" class="cr-btn cr-btn-primary mt-3">Connect Google account</a>
        </div>
    @endif

    @if ($isConnector && $connectionCode)
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connection code</h3>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-muted">
                <li>Install the <strong>Client Reporter</strong> plugin on the {{ $manifest->name }} site.</li>
                <li>Open its settings and paste the connection code below.</li>
                <li>Come back here and press <strong>Save &amp; verify</strong>.</li>
            </ol>
            <input readonly value="{{ $connectionCode }}" onclick="this.select()"
                   class="cr-input mt-3 font-mono text-xs" aria-label="Connection code">
            <p class="mt-1 text-xs text-faint">Keep this secret. Anyone with it can read this site's report data.</p>
        </div>
    @endif

    @if ($workspaceConnection)
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connected via the workspace</h3>
            <p class="mt-1 text-sm text-muted">
                This site uses the shared <strong>{{ $workspaceConnection->name }}</strong> connection — its credentials are
                managed once for every site. You can still choose what this site maps to below.
            </p>
            <a href="{{ route('integrations.workspace.edit', $workspaceConnection) }}" wire:navigate
               class="mt-3 inline-block text-sm font-semibold" style="color:var(--color-accent)">Manage the workspace connection →</a>
        </div>
    @endif

    @if ($integration->setupSteps() !== [] && ! $workspaceConnection)
        <div class="mb-4 max-w-xl overflow-hidden rounded-xl border border-line bg-surface">
            <div class="border-b border-line px-5 py-3"><h3 class="cr-eyebrow">How to connect {{ $manifest->name }}</h3></div>
            <ol class="list-decimal space-y-1.5 px-5 py-4 pl-9 text-sm text-muted marker:font-semibold marker:text-accent">
                @foreach ($integration->setupSteps() as $step)
                    <li>{!! $step !!}</li>
                @endforeach
            </ol>
        </div>
    @endif

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <div>
            <label for="name" class="cr-label">Connection name</label>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        @foreach ($fields as $field)
            <div wire:key="field-{{ $field->key }}">
                <label for="field-{{ $field->key }}" class="cr-label">
                    {{ $field->label }}
                    @unless ($field->required) <span class="text-faint">(optional)</span> @endunless
                </label>

                @if ($field->type === 'select')
                    <select wire:model="values.{{ $field->key }}" id="field-{{ $field->key }}" class="cr-input">
                        <option value="">Select…</option>
                        @foreach ($field->options as $value => $optionLabel)
                            <option value="{{ $value }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                @elseif ($field->type === 'textarea')
                    <textarea wire:model="values.{{ $field->key }}" id="field-{{ $field->key }}" rows="3" class="cr-input"></textarea>
                @else
                    <input wire:model="values.{{ $field->key }}" id="field-{{ $field->key }}"
                           type="{{ $field->type === 'password' ? 'password' : ($field->type === 'url' ? 'url' : 'text') }}"
                           class="cr-input"
                           @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif
                           @if ($field->secret && $connection) autocomplete="off" @endif>
                @endif

                @if ($field->help) <p class="mt-1 text-xs text-faint">{{ $field->help }}</p> @endif
                @if ($field->secret && $connection) <p class="mt-1 text-xs text-faint">Leave blank to keep the saved value.</p> @endif
                @error("values.{$field->key}") <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <button type="submit" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="save">{{ $connection ? 'Save & verify' : 'Connect & verify' }}</span>
                <span wire:loading wire:target="save">Verifying…</span>
            </button>
            <a href="{{ route('sites.show', $site) }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
