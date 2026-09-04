<div>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <x-page-header :title="$client->name" :subtitle="$client->company ?: null">
        <x-slot:actions>
            @can('manage-clients')
                <a href="{{ route('clients.branding', $client) }}" wire:navigate class="cr-btn cr-btn-secondary">Branding</a>
                <a href="{{ route('clients.edit', $client) }}" wire:navigate class="cr-btn cr-btn-secondary">Edit client</a>
            @endcan
            @can('manage-sites')
                <a href="{{ route('sites.create', ['client' => $client->id]) }}" wire:navigate class="cr-btn cr-btn-primary">Add site</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Sites --}}
        <div class="lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-ink">Websites</h2>
            @if ($client->sites->isEmpty())
                <x-empty-state title="No sites yet" description="Add this client's first website to begin connecting services.">
                    @can('manage-sites')
                        <x-slot:action>
                            <a href="{{ route('sites.create', ['client' => $client->id]) }}" wire:navigate class="cr-btn cr-btn-primary">Add site</a>
                        </x-slot:action>
                    @endcan
                </x-empty-state>
            @else
                <div class="cr-card divide-y divide-line">
                    @foreach ($client->sites as $site)
                        <a href="{{ route('sites.show', $site) }}" wire:navigate wire:key="site-{{ $site->id }}"
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-paper">
                            <div class="min-w-0">
                                <div class="font-medium text-ink">{{ $site->name }}</div>
                                <div class="truncate text-xs text-muted">{{ $site->host() }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($site->cms_type)
                                    <x-badge variant="neutral">{{ ucfirst($site->cms_type) }}</x-badge>
                                @endif
                                @if (! $site->is_active)
                                    <x-badge variant="neutral">Inactive</x-badge>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="space-y-6">
            <div class="cr-card px-5 py-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Contact</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="text-ink">{{ $client->contact_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Email</dt>
                        <dd class="text-ink">{{ $client->contact_email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd>@if ($client->is_active) <x-badge variant="ok">Active</x-badge> @else <x-badge variant="neutral">Inactive</x-badge> @endif</dd>
                    </div>
                </dl>
            </div>

            @if ($client->notes)
                <div class="cr-card px-5 py-4">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $client->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <livewire:billing.invoice-panel :client="$client" :key="'invoices-'.$client->id" />
    </div>
</div>
