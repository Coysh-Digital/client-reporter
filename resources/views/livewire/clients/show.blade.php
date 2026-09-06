<div>
    <x-breadcrumbs :items="[['label' => 'Clients', 'href' => route('clients.index')], ['label' => $client->name]]" />

    <x-page-header :title="$client->name" :subtitle="$client->company ?: null">
        <x-slot:actions>
            @can('manage-sites')
                <x-button variant="primary" :href="route('sites.create', ['client' => $client->id])" icon="plus">Add site</x-button>
            @endcan
            @can('manage-clients')
                <x-dropdown>
                    <x-slot:trigger>
                        <button type="button" class="cr-btn cr-btn-secondary" aria-label="More actions for {{ $client->name }}">
                            <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                        </button>
                    </x-slot:trigger>
                    <x-dropdown-item :href="route('clients.edit', $client)" icon="pencil-square">Edit client</x-dropdown-item>
                    <x-dropdown-item :href="route('clients.branding', $client)" icon="palette">Branding</x-dropdown-item>
                    @can('manage-users')
                        <x-dropdown-item :href="route('users.create', ['client' => $client->id])" icon="user-circle">Add portal user</x-dropdown-item>
                    @endcan
                </x-dropdown>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($client->is_active)
        <x-alert variant="warn" class="mb-6">This client is inactive: its sites are not collected or reported on.</x-alert>
    @endunless

    {{-- Status strip --}}
    <div class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-line bg-surface lg:grid-cols-4">
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Sites</div>
            <div class="tnum mt-2 font-serif text-2xl font-semibold text-ink">{{ $strip['sites'] }}</div>
            <div class="mt-1 text-xs text-faint">{{ $strip['activeSites'] }} active</div>
        </div>
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Healthy</div>
            <div class="tnum mt-2 font-serif text-2xl font-semibold text-ink">{{ $strip['healthy'] }}<span class="text-lg font-medium text-faint"> / {{ $strip['activeSites'] }}</span></div>
            <div class="mt-1 text-xs text-faint">of the active sites this month</div>
        </div>
        <div class="border-r border-line px-5 py-4">
            <div class="cr-eyebrow">Integrations</div>
            <div class="tnum mt-2 font-serif text-2xl font-semibold text-ink">{{ $strip['connected'] }}</div>
            @if ($strip['troubled'] > 0)
                <div class="mt-1 text-xs" style="color:var(--color-danger);">{{ $strip['troubled'] }} {{ $strip['troubled'] === 1 ? 'needs' : 'need' }} attention</div>
            @else
                <div class="mt-1 text-xs text-faint">{{ $strip['connected'] > 0 ? 'All connected' : 'None connected yet' }}</div>
            @endif
        </div>
        <div class="px-5 py-4">
            <div class="cr-eyebrow">Reports</div>
            <div class="tnum mt-2 font-serif text-2xl font-semibold text-ink">{{ $reportsTotal }}</div>
            <div class="mt-1 text-xs text-faint">{{ $reportsSent }} sent · {{ $strip['reportsThisPeriod'] }} this month</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">
            {{-- Sites --}}
            <section>
                <h2 class="mb-3 font-serif text-base font-semibold text-ink">Websites</h2>
                @if (empty($sitesSummary))
                    <x-empty-state icon="globe" title="No sites yet" description="Add this client's first website to begin connecting services.">
                        @can('manage-sites')
                            <x-slot:action>
                                <x-button variant="primary" :href="route('sites.create', ['client' => $client->id])" icon="plus">Add site</x-button>
                            </x-slot:action>
                        @endcan
                    </x-empty-state>
                @else
                    <x-table caption="Sites for {{ $client->name }}">
                        <thead>
                            <tr>
                                <x-th>Site</x-th>
                                <x-th>Health</x-th>
                                <x-th>Integrations</x-th>
                                <x-th>Latest report</x-th>
                                <x-th>Schedule</x-th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sitesSummary as $row)
                                @php($site = $row['site'])
                                <tr wire:key="site-{{ $site->id }}">
                                    <x-td>
                                        <a href="{{ route('sites.show', $site) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                                            <x-avatar :name="$site->name" :icon="$site->faviconUrl()" aria-hidden="true" />
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold text-ink">{{ $site->name }}</span>
                                                <span class="block truncate text-xs text-faint">{{ $site->host() }}{{ $site->cms_type ? ' · '.ucfirst($site->cms_type) : '' }}</span>
                                            </span>
                                        </a>
                                    </x-td>
                                    <x-td nowrap>
                                        @if (! $site->is_active)
                                            <x-status-dot variant="neutral" label="Inactive" />
                                        @elseif ($row['health'])
                                            <x-status-dot :variant="$row['health']->badge()" :label="$row['health']->label()" />
                                        @else
                                            <x-status-dot variant="ok" label="Healthy" />
                                        @endif
                                    </x-td>
                                    <x-td nowrap>
                                        <span class="tnum text-muted">{{ $row['connectedIntegrations'] }}</span>
                                        @if ($row['troubledIntegrations'] > 0)
                                            <span class="ml-1 text-xs" style="color:var(--color-danger);">{{ $row['troubledIntegrations'] }} {{ $row['troubledIntegrations'] === 1 ? 'needs' : 'need' }} attention</span>
                                        @endif
                                    </x-td>
                                    <x-td nowrap>
                                        @if ($row['latestReport'])
                                            <a href="{{ $row['latestReport']['url'] }}" wire:navigate class="inline-flex items-center gap-2 text-muted hover:text-ink">
                                                <span class="text-xs">{{ $row['latestReport']['period'] }}</span>
                                                <x-badge :variant="$row['latestReport']['status']->badge()">{{ $row['latestReport']['status']->label() }}</x-badge>
                                            </a>
                                        @else
                                            <span class="text-xs text-faint">None yet</span>
                                        @endif
                                    </x-td>
                                    <x-td nowrap><span class="text-xs text-muted">{{ $row['scheduled'] ?? 'Manual' }}</span></x-td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </section>

            {{-- Recent reports --}}
            <section>
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="font-serif text-base font-semibold text-ink">Recent reports</h2>
                    @if ($reportsTotal > $recentReports->count())
                        <a href="{{ route('reports.index', ['client' => $client->id]) }}" wire:navigate class="cr-link text-xs">View all {{ $reportsTotal }} reports</a>
                    @endif
                </div>
                @if ($recentReports->isEmpty())
                    <x-empty-state icon="file-chart-column" title="No reports yet" description="Reports for this client's sites will appear here once you create one.">
                        @can('manage-reports')
                            @if (! empty($sitesSummary))
                                <x-slot:action>
                                    <x-button variant="primary" :href="route('reports.create')" icon="plus">New report</x-button>
                                </x-slot:action>
                            @endif
                        @endcan
                    </x-empty-state>
                @else
                    <div class="cr-panel divide-y divide-line">
                        @foreach ($recentReports as $report)
                            <a href="{{ route('reports.show', $report) }}" wire:navigate wire:key="report-{{ $report->id }}"
                               class="flex flex-col gap-2 px-5 py-3 transition hover:bg-paper sm:flex-row sm:items-center sm:gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-ink">{{ $report->title }}</div>
                                    <div class="truncate text-xs text-faint">{{ $report->site->name }} · {{ $report->dateRange()->label() }}</div>
                                </div>
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <x-status-dot :variant="$report->periodStatus()->badge()" :label="$report->periodStatus()->label()" />
                                    <span class="whitespace-nowrap text-xs text-faint">
                                        {{ $report->generated_at ? 'Generated '.$report->generated_at->isoFormat('D MMM YYYY') : 'Not generated' }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <livewire:billing.invoice-panel :client="$client" :key="'invoices-'.$client->id" />
            </section>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <div class="cr-card px-5 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="cr-eyebrow">Contact</h3>
                    @can('manage-clients')
                        <a href="{{ route('clients.edit', $client) }}" wire:navigate class="cr-link text-xs">Edit</a>
                    @endcan
                </div>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Name</dt><dd class="min-w-0 truncate text-right text-ink">{{ $client->contact_name ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Email</dt><dd class="min-w-0 truncate text-right">@if ($client->contact_email)<a href="mailto:{{ $client->contact_email }}" class="cr-link">{{ $client->contact_email }}</a>@else <span class="text-ink">—</span>@endif</dd></div>
                    @if ($client->company)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Company</dt><dd class="min-w-0 truncate text-right text-ink">{{ $client->company }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">Status</dt><dd>@if ($client->is_active) <x-badge variant="ok">Active</x-badge> @else <x-badge variant="neutral">Inactive</x-badge> @endif</dd></div>
                </dl>
            </div>

            <div class="cr-card px-5 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="cr-eyebrow">Portal access</h3>
                    @can('manage-users')
                        <a href="{{ route('users.create', ['client' => $client->id]) }}" wire:navigate class="cr-link text-xs">Add user</a>
                    @endcan
                </div>
                @if ($portalUsers->isEmpty())
                    <p class="mt-2 text-sm text-muted">No one from {{ $client->name }} can sign in yet. Portal users see only this client's sites and generated reports.</p>
                @else
                    <ul class="mt-3 divide-y divide-line">
                        @foreach ($portalUsers as $user)
                            <li wire:key="portal-user-{{ $user->id }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-ink">{{ $user->name }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $user->email }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    @if (! $user->is_active)
                                        <x-badge variant="neutral">Inactive</x-badge>
                                    @else
                                        <span class="block text-2xs text-faint">{{ $user->last_login_at ? 'Signed in '.$user->last_login_at->diffForHumans() : 'Never signed in' }}</span>
                                    @endif
                                    @can('manage-users')
                                        <a href="{{ route('users.edit', $user) }}" wire:navigate class="cr-link text-2xs">Manage</a>
                                    @endcan
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($client->notes)
                <div class="cr-card px-5 py-4">
                    <h3 class="cr-eyebrow">Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $client->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
