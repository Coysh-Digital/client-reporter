<div>
    @php $manifest = $integration->manifest(); @endphp

    <x-breadcrumbs :items="[['label' => 'Sites', 'href' => route('sites.index')], ['label' => $site->name, 'href' => route('sites.show', $site)], ['label' => $manifest->name]]" />

    <x-page-header :title="($connection ? 'Manage ' : 'Connect ') . $manifest->name"
                   :subtitle="$manifest->description" />

    @error('verification')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror


    @if ($needsOAuthConnect && $connection)
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connect your account</h3>
            <p class="mt-1 text-sm text-muted">Authorise access so Client Reporter can read this property's analytics.</p>
            <x-button variant="primary" :href="route('integrations.google.connect', $connection)" :navigate="false" class="mt-3">Connect Google account</x-button>
        </div>
    @endif

    @if ($isConnector && ($connectionCode || $hasConnectionCode))
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connection code</h3>
            @if ($connectionCode)
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-muted">
                    <li>Install the <strong>Client Reporter</strong> plugin on the {{ $manifest->name }} site.</li>
                    <li>Open its settings and paste the connection code below.</li>
                    <li>Come back here and press <strong>Save &amp; verify</strong>.</li>
                </ol>
                <div class="mt-3 flex items-center gap-2" x-data="{ copied: false, copy() { navigator.clipboard?.writeText($refs.code.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }">
                    <input readonly value="{{ $connectionCode }}" x-ref="code" x-on:click="$el.select()"
                           class="cr-input font-mono text-xs" aria-label="Connection code">
                    <x-button icon="clipboard-document" x-on:click="copy()" aria-live="polite">
                        <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                    </x-button>
                </div>
                <p class="mt-1 text-xs text-faint">Copy it now — it is shown only this once. Anyone with it can read this site's report data.</p>
            @else
                <p class="mt-1 text-sm text-muted">
                    A connection code is set on this connection and in the plugin. It is not shown again;
                    if it has been lost or exposed, generate a new one and paste it into the plugin.
                </p>
                <x-confirm-button action="regenerateConnectionCode" title="Generate a new connection code?" message="The plugin stops responding until the new code is pasted into its settings." confirm="Generate new code" class="cr-btn cr-btn-secondary mt-3">Generate a new connection code</x-confirm-button>
            @endif
        </div>
    @endif

    @if ($workspaceConnection)
        <div class="mb-4 cr-card border-accent/30 bg-accent-soft/40 px-5 py-4">
            <h3 class="text-sm font-semibold text-ink">Connected via the workspace</h3>
            <p class="mt-1 text-sm text-muted">
                This site uses the shared <strong>{{ $workspaceConnection->name }}</strong> connection — its credentials are
                managed once for every site. You can still choose what this site maps to below.
            </p>
            <a href="{{ route('integrations.workspace.edit', $workspaceConnection) }}" wire:navigate class="cr-link mt-3 inline-block text-sm">Manage the workspace connection →</a>
        </div>
    @endif

    @if ($integration->setupSteps() !== [] && ! $workspaceConnection)
        <details class="cr-panel mb-4 max-w-xl" open>
            <summary class="cr-panel-header cursor-pointer select-none"><h3 class="cr-eyebrow">How to connect {{ $manifest->name }}</h3></summary>
            <ol class="list-decimal space-y-1.5 px-5 py-4 pl-9 text-sm text-muted marker:font-semibold marker:text-accent">
                @foreach ($integration->setupSteps() as $step)
                    <li>{{ \App\Support\Html::inline($step) }}</li>
                @endforeach
            </ol>
            @if ($integration->connectorDownloadUrl())
                <div class="border-t border-line px-5 py-4">
                    <x-button :href="$integration->connectorDownloadUrl()" :navigate="false" target="_blank" rel="noopener noreferrer" icon="arrow-up-right-from-square">
                        Download the plugin
                    </x-button>
                </div>
            @endif
        </details>
    @endif

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Connection name" for="name" required>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
        </x-field>

        @foreach ($fields as $field)
            <x-field :label="$field->label" :for="'field-'.$field->key" :name="'values.'.$field->key" :optional="! $field->required" wire:key="field-{{ $field->key }}"
                     :help="trim(($field->help ?? '').(($field->secret && $connection) ? ' Leave blank to keep the saved value.' : '')) ?: null">

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
            </x-field>
        @endforeach

        <x-field label="Update frequency" for="collection-interval" name="collectionInterval"
                 help="How often this connection's data is refreshed. 'Use default' follows the workspace, then the global collection interval in Settings.">
            <select wire:model="collectionInterval" id="collection-interval" class="cr-input">
                @foreach ($this->frequencyOptions() as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
        </x-field>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ $connection ? 'Save & verify' : 'Connect & verify' }}</span>
                <span wire:loading wire:target="save">Verifying…</span>
            </x-button>
            <x-button :href="route('sites.show', $site)">Cancel</x-button>
        </div>
    </form>
</div>
