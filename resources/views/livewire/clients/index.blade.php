<div>
    <x-page-header title="Clients" subtitle="The businesses you build reports for." eyebrow="Portfolio">
        <x-slot:actions>
            @can('manage-clients')
                <a href="{{ route('clients.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New client
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 rounded-lg border border-line bg-surface p-0.5">
            @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                <button type="button" wire:click="setStatus('{{ $key }}')"
                        @class([
                            'rounded-md px-3 py-1.5 text-[13px] font-medium transition',
                            'bg-accent-soft text-accent' => $status === $key,
                            'text-muted hover:text-ink' => $status !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search clients…"
               class="cr-input max-w-xs">
    </div>

    @if ($clients->isEmpty())
        <x-empty-state icon="building-user" title="No clients yet"
                       description="Add your first client to start connecting their websites and building reports.">
            @can('manage-clients')
                <x-slot:action>
                    <a href="{{ route('clients.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New client
                </a>
                </x-slot:action>
            @endcan
        </x-empty-state>
    @else
        <div class="cr-panel">
            <div class="hidden items-center gap-4 border-b border-line px-5 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint sm:grid sm:grid-cols-[minmax(0,1fr)_80px_150px_120px]">
                <span>Client</span><span>Sites</span><span>Health</span><span>Status</span>
            </div>
            @foreach ($clients as $client)
                @php $health = $healthByClient[$client->id] ?? null; @endphp
                <div wire:key="client-{{ $client->id }}"
                     class="flex flex-col gap-3 px-5 py-3.5 hover:bg-paper sm:grid sm:grid-cols-[minmax(0,1fr)_80px_150px_120px] sm:items-center sm:gap-4"
                     @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                    <a href="{{ route('clients.show', $client) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                        <x-avatar :name="$client->name" size="lg" />
                        <span class="min-w-0">
                            <span class="block truncate text-[14.5px] font-semibold text-ink">{{ $client->name }}</span>
                            @if ($client->company)
                                <span class="block truncate text-[12.5px] text-faint">{{ $client->company }}</span>
                            @endif
                        </span>
                    </a>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:contents">
                        <span class="tnum text-[13.5px] text-muted">{{ $client->sites_count }} {{ Str::plural('site', $client->sites_count) }}</span>
                        <span>
                            @if ($health)
                                <x-status-dot :variant="$health->badge()" :label="$health->label()" />
                            @else
                                <span class="text-xs text-faint">No sites</span>
                            @endif
                        </span>
                        <span class="flex items-center justify-between gap-2">
                            @if ($client->is_active)
                                <x-badge variant="ok">Active</x-badge>
                            @else
                                <x-badge variant="neutral">Inactive</x-badge>
                            @endif
                            @can('manage-clients')
                                <button wire:click="delete({{ $client->id }})"
                                        wire:confirm="Delete {{ $client->name }} and all its sites, integrations and reports? This cannot be undone."
                                        class="text-faint hover:text-danger" title="Delete">
                                    <x-icon name="trash-can" class="h-3.5 w-3.5" />
                                </button>
                            @endcan
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
</div>
