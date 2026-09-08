<div {{ $polling ? 'wire:poll.10s' : '' }}>
    @if ($connections->isEmpty())
        <x-empty-state icon="plug" title="No integrations connected"
                       description="Connect analytics, uptime and CMS services to start collecting data for this site.">
            @can('manage-integrations')
                <x-slot:action>
                    <x-button variant="primary" icon="plus" x-on:click="$dispatch('open-add-integration')">Add integration</x-button>
                </x-slot:action>
            @endcan
        </x-empty-state>
    @else
        <div class="cr-panel divide-y divide-line">
            @foreach ($connections as $connection)
                @php
                    $manifest = $connection->integration()?->manifest();
                    $state = $states[$connection->id];
                    $headline = $headlines[$connection->id] ?? null;
                    $trend = $trends[$connection->id] ?? null;
                    $apiKeyNote = $apiKeyNotes[$connection->id] ?? null;
                @endphp
                <div wire:key="conn-{{ $connection->id }}" x-data="{ open: false }" class="px-5 py-3.5">
                    <div class="flex items-center gap-4">
                        <x-avatar :name="$manifest?->name ?? $connection->integration_key" size="lg" :icon="$manifest?->iconUrl()" aria-hidden="true" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="truncate text-md font-semibold text-ink">{{ $connection->name }}</span>
                                <span class="text-xs text-faint">{{ $manifest?->name ?? $connection->integration_key }}</span>
                                @if ($connection->usesWorkspace())
                                    <x-badge variant="accent">Workspace</x-badge>
                                @endif
                                @if ($apiKeyNote === 'workspace')
                                    <x-badge variant="neutral" title="Using the Google API key from the workspace PageSpeed connection.">Workspace API key</x-badge>
                                @elseif ($apiKeyNote === 'own')
                                    <x-badge variant="neutral" title="Using this connection's own Google API key.">Own API key</x-badge>
                                @elseif ($apiKeyNote === 'none')
                                    <x-badge variant="warn" title="No Google API key set, so PageSpeed calls run anonymously and get rate-limited. Add a key on the workspace PageSpeed connection.">No API key</x-badge>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <x-status-dot :variant="$state->variant" :label="$state->label" :class="$state->syncing ? 'animate-pulse' : ''" />
                                <span class="text-xs text-faint">{{ $state->timing() }}</span>
                            </div>
                            @if ($state->detail)
                                <p class="mt-1.5 text-xs" style="color:var(--color-{{ $state->variant }});">{{ $state->detail }}</p>
                            @endif
                        </div>

                        @if ($headline || $trend)
                            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'"
                                    class="hidden shrink-0 items-center gap-3 rounded-lg px-2 py-1 text-right transition hover:bg-paper sm:flex"
                                    aria-label="{{ $trend ? 'Show the daily trend for '.$connection->name : 'Headline figure for '.$connection->name }}">
                                @if ($headline)
                                    <span>
                                        <span class="tnum block text-md font-semibold text-ink">{{ $headline['value'] }}</span>
                                        <span class="block text-2xs text-faint">{{ $headline['label'] }} · {{ $headline['period'] }}</span>
                                    </span>
                                @endif
                                @if ($trend)
                                    <x-sparkline :points="$trend['spark']" :label="$trend['label']" />
                                @endif
                            </button>
                        @endif

                        @can('manage-integrations')
                            <div class="flex shrink-0 items-center gap-1">
                                <x-dropdown>
                                    <x-slot:trigger>
                                        <button type="button" class="cr-btn-icon" aria-label="Actions for {{ $connection->name }}">
                                            <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                        </button>
                                    </x-slot:trigger>
                                    @if ($state->action === 'reconnect')
                                        <x-dropdown-item :href="route('integrations.edit', $connection)" icon="key">Reconnect</x-dropdown-item>
                                    @endif
                                    <x-dropdown-item wire:click="collectNow({{ $connection->id }})" icon="arrow-path" :disabled="$state->syncing">
                                        {{ $state->syncing ? 'Collecting…' : ($state->action === 'retry' ? 'Retry now' : 'Collect now') }}
                                    </x-dropdown-item>
                                    <x-dropdown-item :href="route('integrations.edit', $connection)" icon="pencil-square">Manage</x-dropdown-item>
                                    <div class="cr-menu-separator"></div>
                                    <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                        action="disconnect({{ $connection->id }})"
                                        title="Disconnect {{ $connection->name }}?"
                                        message="Data collected from this connection is removed. Reports already generated keep their snapshots."
                                        confirm="Disconnect" :danger="true">
                                        <x-icon name="x-circle" class="h-3.5 w-3.5 shrink-0" /> Disconnect
                                    </x-confirm-button>
                                </x-dropdown>
                            </div>
                        @endcan
                    </div>

                    @if ($trend)
                        <div x-show="open" x-cloak class="mt-3 pl-[54px]">
                            <div class="rounded-lg border border-line bg-paper/40 p-3" wire:ignore>
                                <p class="mb-2 text-2xs font-semibold uppercase tracking-wide text-faint">{{ $trend['label'] }}</p>
                                <div class="h-40" x-data="crLineChart(@js($trend))">
                                    <canvas x-ref="canvas" role="img" aria-label="{{ $trend['label'] }}, {{ count($trend['data']) }} daily values from {{ $trend['labels'][0] }} to {{ end($trend['labels']) }}"></canvas>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
