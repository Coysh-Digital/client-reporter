<div>
    @php $manifest = $integration->manifest(); @endphp

    <x-breadcrumbs :items="[['label' => 'Integrations', 'href' => route('integrations.index')], ['label' => $manifest->name]]" />

    <x-page-header :title="'Connect ' . $manifest->name . ' — workspace'"
                   :subtitle="'One ' . $manifest->name . ' connection for every ' . ($mapsToClient ? 'client' : 'site') . '. ' . $manifest->description" />


    @error('verification')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    @if ($phase === 'credentials')
        @if ($integration->workspaceSetupSteps() !== [])
            <details class="cr-panel mb-4 max-w-xl" open>
                <summary class="cr-panel-header cursor-pointer select-none"><h3 class="cr-eyebrow">How to connect {{ $manifest->name }}</h3></summary>
                <ol class="list-decimal space-y-1.5 px-5 py-4 pl-9 text-sm text-muted marker:font-semibold marker:text-accent">
                    @foreach ($integration->workspaceSetupSteps() as $step)
                        <li>{{ \App\Support\Html::inline($step) }}</li>
                    @endforeach
                </ol>
            </details>
        @endif

        <form wire:submit="connect" class="cr-card max-w-xl px-6 py-6 space-y-5">
            <x-field label="Connection name" for="name" required>
                <input wire:model="name" id="name" type="text" class="cr-input" required>
            </x-field>

            @foreach ($integration->accountConfigFields() as $field)
                <x-field :label="$field->label" :for="'wfield-'.$field->key" :name="'values.'.$field->key" :optional="! $field->required" :help="$field->help" wire:key="wfield-{{ $field->key }}">
                    <input wire:model="values.{{ $field->key }}" id="wfield-{{ $field->key }}"
                           type="{{ $field->type === 'password' ? 'password' : ($field->type === 'url' ? 'url' : 'text') }}"
                           class="cr-input"
                           @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif>
                </x-field>
            @endforeach

            <div class="flex items-center gap-3 border-t border-line pt-5">
                <x-button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="connect">
                        @if ($needsOAuthConnect)
                            Connect {{ $manifest->name }} account
                        @elseif ($integration->manifest()->authMethod->value === 'oauth')
                            Find {{ $mapsToClient ? 'clients' : 'sites' }}
                        @else
                            Connect &amp; find {{ $mapsToClient ? 'clients' : 'sites' }}
                        @endif
                    </span>
                    <span wire:loading wire:target="connect">Connecting…</span>
                </x-button>
                <x-button :href="route('integrations.index')">Cancel</x-button>
            </div>
        </form>
    @else
        <x-alert variant="ok" class="mb-4">
            Connected. Found {{ count($discovered) }} {{ Str::plural('item', count($discovered)) }} on your {{ $manifest->name }} account —
            {{ $matchedCount }} auto-matched to {{ Str::plural($mapsToClient ? 'client' : 'site', $matchedCount) }}
            by {{ $mapsToClient ? 'email or name' : 'URL' }}. Adjust any below, then create the connections.
        </x-alert>

        <form wire:submit="confirm" class="cr-card max-w-3xl px-6 py-6">
            @if ($discovered === [])
                <p class="text-sm text-muted">No {{ $mapsToClient ? 'contacts' : 'monitors or properties' }} were found on this account yet.</p>
            @else
                @if ($mapsToClient)
                    <div class="mb-3 flex justify-end">
                        <x-button size="sm" icon="plus" wire:click="createNewForUnmapped">Create new clients for all unmapped</x-button>
                    </div>
                @endif

                <x-table :caption="$manifest->name.' items found'">
                    <thead>
                        <tr>
                            <x-th>{{ $manifest->name }} item</x-th>
                            <x-th>Maps to {{ $mapsToClient ? 'client' : 'site' }}</x-th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discovered as $index => $entity)
                            <tr wire:key="disc-{{ $index }}">
                                <x-td>
                                    <div class="font-medium text-ink">{{ $entity['label'] }}</div>
                                    @if ($entity['url'] || $entity['email'])
                                        <div class="text-xs text-faint">{{ $entity['url'] ?? $entity['email'] }}</div>
                                    @endif
                                </x-td>
                                <x-td>
                                    <select wire:model="assignments.{{ $index }}" class="cr-input max-w-xs" aria-label="Map {{ $entity['label'] }} to a {{ $mapsToClient ? 'client' : 'site' }}">
                                        <option value="">— Skip —</option>
                                        @if ($mapsToClient)
                                            <option value="new">＋ Create new client</option>
                                        @endif
                                        @foreach ($options as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </x-td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif

            <div class="mt-6 flex items-center gap-3 border-t border-line pt-5">
                <x-button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="confirm">Create connections</span>
                    <span wire:loading wire:target="confirm">Saving…</span>
                </x-button>
                <x-button :href="route('integrations.index')">Cancel</x-button>
            </div>
        </form>
    @endif
</div>
