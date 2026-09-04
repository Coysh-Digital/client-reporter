<div>
    @php $manifest = $integration->manifest(); @endphp

    <div class="mb-2 text-sm text-muted">
        <a href="{{ route('integrations.index') }}" wire:navigate class="hover:text-ink">Integrations</a>
        <span class="text-faint">/</span> Connect for the whole workspace
    </div>

    <x-page-header :title="'Connect ' . $manifest->name . ' — workspace'"
                   :subtitle="'One ' . $manifest->name . ' connection for every ' . ($mapsToClient ? 'client' : 'site') . '. ' . $manifest->description" />

    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    @error('verification')
        <div class="mb-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger">{{ $message }}</div>
    @enderror

    @if ($phase === 'credentials')
        @if ($integration->workspaceSetupSteps() !== [])
            <div class="mb-4 max-w-xl overflow-hidden rounded-xl border border-line bg-surface">
                <div class="border-b border-line px-5 py-3"><h3 class="cr-eyebrow">How to connect {{ $manifest->name }}</h3></div>
                <ol class="list-decimal space-y-1.5 px-5 py-4 pl-9 text-sm text-muted marker:font-semibold marker:text-accent">
                    @foreach ($integration->workspaceSetupSteps() as $step)
                        <li>{!! $step !!}</li>
                    @endforeach
                </ol>
            </div>
        @endif

        <form wire:submit="connect" class="cr-card max-w-xl px-6 py-6 space-y-5">
            <div>
                <label for="name" class="cr-label">Connection name</label>
                <input wire:model="name" id="name" type="text" class="cr-input" required>
                @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            @foreach ($integration->accountConfigFields() as $field)
                <div wire:key="wfield-{{ $field->key }}">
                    <label for="wfield-{{ $field->key }}" class="cr-label">
                        {{ $field->label }}
                        @unless ($field->required) <span class="text-faint">(optional)</span> @endunless
                    </label>
                    <input wire:model="values.{{ $field->key }}" id="wfield-{{ $field->key }}"
                           type="{{ $field->type === 'password' ? 'password' : ($field->type === 'url' ? 'url' : 'text') }}"
                           class="cr-input"
                           @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif>
                    @if ($field->help) <p class="mt-1 text-xs text-faint">{{ $field->help }}</p> @endif
                    @error("values.{$field->key}") <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="flex items-center gap-3 border-t border-line pt-5">
                <button type="submit" class="cr-btn cr-btn-primary">
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
                </button>
                <a href="{{ route('integrations.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
            </div>
        </form>
    @else
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">
            Connected. Found {{ count($discovered) }} {{ Str::plural('item', count($discovered)) }} on your {{ $manifest->name }} account —
            {{ $matchedCount }} auto-matched to {{ Str::plural($mapsToClient ? 'client' : 'site', $matchedCount) }}
            by {{ $mapsToClient ? 'email or name' : 'URL' }}. Adjust any below, then create the connections.
        </div>

        <form wire:submit="confirm" class="cr-card max-w-3xl px-6 py-6">
            @if ($discovered === [])
                <p class="text-sm text-muted">No {{ $mapsToClient ? 'contacts' : 'monitors or properties' }} were found on this account yet.</p>
            @else
                @if ($mapsToClient)
                    <div class="mb-3 flex justify-end">
                        <button type="button" wire:click="createNewForUnmapped" class="cr-btn cr-btn-secondary text-xs">
                            ＋ Create new clients for all unmapped
                        </button>
                    </div>
                @endif

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wide text-faint">
                            <th class="pb-2 font-medium">{{ $manifest->name }} item</th>
                            <th class="pb-2 font-medium">Maps to {{ $mapsToClient ? 'client' : 'site' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($discovered as $index => $entity)
                            <tr wire:key="disc-{{ $index }}">
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-ink">{{ $entity['label'] }}</div>
                                    @if ($entity['url'] || $entity['email'])
                                        <div class="text-xs text-faint">{{ $entity['url'] ?? $entity['email'] }}</div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <select wire:model="assignments.{{ $index }}" class="cr-input max-w-xs">
                                        <option value="">— Skip —</option>
                                        @if ($mapsToClient)
                                            <option value="new">＋ Create new client</option>
                                        @endif
                                        @foreach ($options as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="mt-6 flex items-center gap-3 border-t border-line pt-5">
                <button type="submit" class="cr-btn cr-btn-primary">
                    <span wire:loading.remove wire:target="confirm">Create connections</span>
                    <span wire:loading wire:target="confirm">Saving…</span>
                </button>
                <a href="{{ route('integrations.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
            </div>
        </form>
    @endif
</div>
