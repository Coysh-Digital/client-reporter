<div>
    <x-page-header title="Clients" subtitle="The businesses you build reports for." eyebrow="Portfolio">
        <x-slot:actions>
            @can('manage-clients')
                <x-button variant="primary" :href="route('clients.create')" icon="plus">New client</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-segmented :options="['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" :value="$status" action="setStatus" label="Filter clients by status" />
        <label class="sr-only" for="clients-search">Search clients</label>
        <input wire:model.live.debounce.300ms="search" id="clients-search" type="search" placeholder="Search clients…" class="cr-input max-w-xs">
    </div>

    @if ($clients->isEmpty())
        @if ($search !== '' || $status !== 'all')
            <x-empty-state icon="magnifying-glass" title="No clients match" description="Try a different search or clear the filter.">
                <x-slot:action>
                    <x-button wire:click="$set('search', ''); $set('status', 'all')">Clear filters</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state icon="building-user" title="No clients yet"
                           description="Add your first client to start connecting their websites and building reports.">
                @can('manage-clients')
                    <x-slot:action>
                        <x-button variant="primary" :href="route('clients.create')" icon="plus">New client</x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    @else
        <x-table caption="Clients">
            <thead>
                <tr>
                    <x-th sort="name" :current="$this->currentSort()" :direction="$this->currentDirection()">Client</x-th>
                    <x-th sort="sites" :current="$this->currentSort()" :direction="$this->currentDirection()">Sites</x-th>
                    <x-th>Health</x-th>
                    <x-th sort="status" :current="$this->currentSort()" :direction="$this->currentDirection()">Status</x-th>
                    <x-th><span class="sr-only">Actions</span></x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clients as $client)
                    @php $health = $healthByClient[$client->id] ?? null; @endphp
                    <tr wire:key="client-{{ $client->id }}">
                        <x-td>
                            <a href="{{ route('clients.show', $client) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                                <x-avatar :name="$client->name" size="lg" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block truncate text-md font-semibold text-ink">{{ $client->name }}</span>
                                    @if ($client->company)
                                        <span class="block truncate text-xs text-faint">{{ $client->company }}</span>
                                    @endif
                                </span>
                            </a>
                        </x-td>
                        <x-td nowrap><span class="tnum text-muted">{{ $client->sites_count }} {{ Str::plural('site', $client->sites_count) }}</span></x-td>
                        <x-td nowrap>
                            @if ($health)
                                <x-status-dot :variant="$health->badge()" :label="$health->label()" />
                            @else
                                <span class="text-xs text-faint">No sites</span>
                            @endif
                        </x-td>
                        <x-td nowrap>
                            @if ($client->is_active)
                                <x-badge variant="ok">Active</x-badge>
                            @else
                                <x-badge variant="neutral">Inactive</x-badge>
                            @endif
                        </x-td>
                        <x-td align="right" nowrap>
                            @can('manage-clients')
                                <x-dropdown>
                                    <x-slot:trigger>
                                        <button type="button" class="cr-btn-icon" aria-label="Actions for {{ $client->name }}">
                                            <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                        </button>
                                    </x-slot:trigger>
                                    <x-dropdown-item :href="route('clients.show', $client)" icon="building-user">Open</x-dropdown-item>
                                    <x-dropdown-item :href="route('clients.edit', $client)" icon="pencil-square">Edit</x-dropdown-item>
                                    <x-dropdown-item :href="route('clients.branding', $client)" icon="palette">Branding</x-dropdown-item>
                                    <div class="cr-menu-separator"></div>
                                    <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                        action="delete({{ $client->id }})"
                                        title="Delete {{ $client->name }}?"
                                        message="This removes the client with all {{ $client->sites_count }} {{ Str::plural('site', $client->sites_count) }}, their integrations and every report. It cannot be undone."
                                        confirm="Delete client" :danger="true">
                                        <x-icon name="trash-can" class="h-3.5 w-3.5 shrink-0" /> Delete
                                    </x-confirm-button>
                                </x-dropdown>
                            @endcan
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="mt-4">{{ $clients->links('vendor.pagination.cr') }}</div>
    @endif
</div>
