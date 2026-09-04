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
            @if (empty($sitesSummary))
                <x-empty-state title="No sites yet" description="Add this client's first website to begin connecting services.">
                    @can('manage-sites')
                        <x-slot:action>
                            <a href="{{ route('sites.create', ['client' => $client->id]) }}" wire:navigate class="cr-btn cr-btn-primary">Add site</a>
                        </x-slot:action>
                    @endcan
                </x-empty-state>
            @else
                <div class="cr-card divide-y divide-line">
                    @foreach ($sitesSummary as $row)
                        @php($site = $row['site'])
                        <a href="{{ route('sites.show', $site) }}" wire:navigate wire:key="site-{{ $site->id }}"
                           class="block px-5 py-3.5 hover:bg-paper">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    @if ($row['health'])
                                        <x-status-dot :variant="$row['health']->badge()" :title="$row['health']->label()" />
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-ink">{{ $site->name }}</div>
                                        <div class="truncate text-xs text-muted">{{ $site->host() }}</div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    @if ($site->cms_type)
                                        <x-badge variant="neutral">{{ ucfirst($site->cms_type) }}</x-badge>
                                    @endif
                                    @if (! $site->is_active)
                                        <x-badge variant="neutral">Inactive</x-badge>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 pl-[26px] text-xs text-faint">
                                <span>{{ $row['connectedIntegrations'] }} {{ Str::plural('integration', $row['connectedIntegrations']) }} connected</span>
                                <span>{{ $row['reportsCount'] }} {{ Str::plural('report', $row['reportsCount']) }}</span>
                                @if ($row['scheduled'])
                                    <span>{{ $row['scheduled'] }} schedule</span>
                                @endif
                                @if ($row['latestReport'])
                                    <span class="inline-flex items-center gap-1.5">
                                        Latest: {{ $row['latestReport']['period'] }}
                                        <x-badge :variant="$row['latestReport']['status']->badge()">{{ $row['latestReport']['status']->label() }}</x-badge>
                                    </span>
                                @else
                                    <span>No reports yet</span>
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

            <div class="cr-card px-5 py-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Reporting</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-muted">Websites</dt>
                        <dd class="text-ink">{{ count($sitesSummary) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Reports generated</dt>
                        <dd class="text-ink">{{ $reportsTotal }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Reports sent</dt>
                        <dd class="text-ink">{{ $reportsSent }}</dd>
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

    {{-- Report history --}}
    @if (! empty($reportHistory))
        <div class="mt-8">
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="text-sm font-semibold text-ink">Report history</h2>
                <span class="text-xs text-faint">Across all sites, newest first</span>
            </div>
            <div class="cr-card divide-y divide-line">
                @foreach ($reportHistory as $report)
                    <a href="{{ $report['url'] }}" wire:navigate wire:key="report-{{ $report['id'] }}"
                       class="flex flex-col gap-2 px-5 py-3 hover:bg-paper sm:flex-row sm:items-center sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-ink">{{ $report['site'] }}</div>
                            <div class="truncate text-xs text-muted">{{ $report['period'] }}</div>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4">
                            <x-badge :variant="$report['status']->badge()">{{ $report['status']->label() }}</x-badge>
                            <span class="whitespace-nowrap text-xs text-faint">
                                @if ($report['generatedAt'])
                                    Generated {{ $report['generatedAt']->isoFormat('D MMM YYYY') }}
                                @else
                                    Not generated
                                @endif
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8">
        <livewire:billing.invoice-panel :client="$client" :key="'invoices-'.$client->id" />
    </div>
</div>
