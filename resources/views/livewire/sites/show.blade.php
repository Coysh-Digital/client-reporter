<div>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-2 text-sm text-muted">
        <a href="{{ route('clients.show', $site->client) }}" wire:navigate class="hover:text-ink">{{ $site->client->name }}</a>
        <span class="text-faint">/</span> {{ $site->name }}
    </div>

    <x-page-header :title="$site->name">
        <x-slot:actions>
            <a href="{{ $site->url }}" target="_blank" rel="noopener" class="cr-btn cr-btn-secondary">Visit site</a>
            @can('manage-sites')
                <a href="{{ route('sites.branding', $site) }}" wire:navigate class="cr-btn cr-btn-secondary">Branding</a>
                <a href="{{ route('sites.edit', $site) }}" wire:navigate class="cr-btn cr-btn-secondary">Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Integrations --}}
            <div>
                <h2 class="mb-3 text-sm font-semibold text-ink">Integrations</h2>
                <livewire:integrations.site-panel :site="$site" :key="'panel-'.$site->id" />
            </div>

            {{-- Reports --}}
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">Reports</h2>
                    @can('manage-reports')
                        <a href="{{ route('reports.create', ['site' => $site->id]) }}" wire:navigate class="text-sm text-accent hover:underline">+ New report</a>
                    @endcan
                </div>
                @php $siteReports = $site->reports()->latest()->take(5)->get(); @endphp
                @if ($siteReports->isEmpty())
                    <x-empty-state title="No reports yet"
                                   description="Once services are connected you can build and send branded reports." />
                @else
                    <div class="cr-card divide-y divide-line">
                        @foreach ($siteReports as $report)
                            <a href="{{ route('reports.show', $report) }}" wire:navigate wire:key="rep-{{ $report->id }}"
                               class="flex items-center justify-between px-5 py-3 hover:bg-paper">
                                <div>
                                    <div class="font-medium text-ink">{{ $report->title }}</div>
                                    <div class="text-xs text-muted">{{ $report->dateRange()->label() }}</div>
                                </div>
                                @if ($report->status === 'final')
                                    <x-badge variant="ok">Generated</x-badge>
                                @else
                                    <x-badge variant="neutral">Draft</x-badge>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="cr-card px-5 py-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-muted">URL</dt><dd class="truncate text-ink">{{ $site->host() }}</dd></div>
                    <div><dt class="text-muted">CMS</dt><dd class="text-ink">{{ $site->cms_type ? ucfirst($site->cms_type) : 'Unknown' }}</dd></div>
                    <div><dt class="text-muted">Environment</dt><dd class="text-ink capitalize">{{ $site->environment }}</dd></div>
                    <div><dt class="text-muted">Timezone</dt><dd class="text-ink">{{ $site->timezone }}</dd></div>
                    <div><dt class="text-muted">Status</dt><dd>@if ($site->is_active) <x-badge variant="ok">Active</x-badge> @else <x-badge variant="neutral">Inactive</x-badge> @endif</dd></div>
                </dl>
            </div>

            @can('manage-sites')
                <div class="cr-card px-5 py-4">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Danger zone</h3>
                    <p class="mt-2 text-sm text-muted">Deleting a site removes its integrations, data and reports.</p>
                    <button wire:click="delete"
                            wire:confirm="Delete {{ $site->name }} and all its data? This cannot be undone."
                            class="cr-btn cr-btn-secondary mt-3 text-danger">Delete site</button>
                </div>
            @endcan
        </div>
    </div>
</div>
