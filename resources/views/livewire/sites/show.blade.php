<div>
    <x-breadcrumbs :items="[['label' => 'Clients', 'href' => route('clients.index')], ['label' => $site->client->name, 'href' => route('clients.show', $site->client)], ['label' => $site->name]]" />

    <x-page-header :title="$site->name">
        <x-slot:eyebrow>
            <span class="flex items-center gap-2">
                @if ($site->faviconUrl())
                    <img src="{{ $site->faviconUrl() }}" alt="" class="h-4 w-4 rounded-sm" />
                @endif
                <a href="{{ $site->url }}" target="_blank" rel="noopener" class="cr-link">{{ $site->host() }}</a>
            </span>
        </x-slot:eyebrow>
        <x-slot:actions>
            <x-button :href="$site->url" :navigate="false" target="_blank" rel="noopener" icon="arrow-up-right-from-square">Visit site</x-button>
            @can('manage-reports')
                <x-button :href="route('reports.create', ['site' => $site->id])" icon="file-chart-column">New report</x-button>
            @endcan
            @can('manage-sites')
                <x-dropdown>
                    <x-slot:trigger>
                        <button type="button" class="cr-btn cr-btn-secondary" aria-label="More actions for {{ $site->name }}">
                            <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                        </button>
                    </x-slot:trigger>
                    <x-dropdown-item :href="route('sites.edit', $site)" icon="pencil-square">Edit site</x-dropdown-item>
                    <x-dropdown-item :href="route('sites.branding', $site)" icon="palette">Branding</x-dropdown-item>
                </x-dropdown>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Status strip --}}
    <div class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-line bg-surface lg:grid-cols-4">
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Health</div>
            <div class="mt-2 flex items-center gap-2">
                <x-status-dot :variant="$site->is_active ? $health->badge() : 'neutral'" class="text-md font-semibold" :label="$site->is_active ? $health->label() : 'Inactive'" />
            </div>
            <div class="mt-1 text-xs text-faint">{{ $site->is_active ? 'Uptime, updates and connections this month' : 'Not collected or reported on' }}</div>
        </div>
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Integrations</div>
            <div class="tnum mt-2 font-serif text-2xl font-semibold text-ink">{{ $summary['connected'] }}</div>
            @if ($summary['attention'] > 0)
                <div class="mt-1 text-xs" style="color:var(--color-danger);">{{ $summary['attention'] }} {{ $summary['attention'] === 1 ? 'needs' : 'need' }} attention</div>
            @else
                <div class="mt-1 text-xs text-faint">{{ $summary['connected'] > 0 ? 'All connected' : 'None connected yet' }}</div>
            @endif
        </div>
        <div class="border-r border-line px-5 py-4">
            <div class="cr-eyebrow">Last collected</div>
            <div class="mt-2 text-md font-semibold text-ink">{{ $summary['lastCollected']?->diffForHumans() ?? '—' }}</div>
            <div class="mt-1 text-xs text-faint">
                @if ($summary['nextDue'])
                    {{ $summary['nextDue']->isPast() ? 'Next collection due now' : 'Next due '.$summary['nextDue']->diffForHumans() }}
                @else
                    Nothing scheduled
                @endif
            </div>
        </div>
        <div class="px-5 py-4">
            <div class="cr-eyebrow">Reporting</div>
            <div class="mt-2 text-md font-semibold text-ink">{{ $schedule ? $schedule['frequency'] : 'Manual' }}</div>
            <div class="mt-1 text-xs text-faint">
                @if ($schedule)
                    {{ $schedule['template'] ? 'Template: '.$schedule['template'] : 'Default sections' }}
                @else
                    {{ $reportCount }} {{ Str::plural('report', $reportCount) }} so far
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">
            {{-- Integrations --}}
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-serif text-base font-semibold text-ink">Integrations</h2>
                    @can('manage-integrations')
                        <x-button size="sm" icon="plus" x-on:click="$dispatch('open-add-integration')" :disabled="$available === []">Add integration</x-button>
                    @endcan
                </div>
                <livewire:integrations.site-panel :site="$site" :key="'panel-'.$site->id" />
            </section>

            {{-- Reports --}}
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-serif text-base font-semibold text-ink">Reports</h2>
                    @if ($reportCount > $recentReports->count())
                        <a href="{{ route('reports.index', ['site' => $site->id]) }}" wire:navigate class="cr-link text-xs">View all {{ $reportCount }} reports</a>
                    @endif
                </div>
                @if ($recentReports->isEmpty())
                    <x-empty-state icon="file-chart-column" title="No reports yet"
                                   description="Once services are connected you can build and send a branded report for this site.">
                        @can('manage-reports')
                            <x-slot:action>
                                <x-button variant="primary" :href="route('reports.create', ['site' => $site->id])" icon="plus">New report</x-button>
                            </x-slot:action>
                        @endcan
                    </x-empty-state>
                @else
                    <div class="cr-panel divide-y divide-line">
                        @foreach ($recentReports as $report)
                            <a href="{{ route('reports.show', $report) }}" wire:navigate wire:key="rep-{{ $report->id }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-paper">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-ink">{{ $report->title }}</div>
                                    <div class="text-xs text-faint">{{ $report->dateRange()->label() }}{{ $report->generated_at ? ' · generated '.$report->generated_at->diffForHumans() : '' }}</div>
                                </div>
                                @if ($report->isGenerating())
                                    <x-status-dot variant="info" :label="$report->generation_status->label()" />
                                @elseif ($report->generationFailed())
                                    <x-status-dot variant="danger" label="Generation failed" />
                                @else
                                    <x-status-dot :variant="$report->periodStatus()->badge()" :label="$report->periodStatus()->label()" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <div class="cr-card px-5 py-4">
                <h3 class="cr-eyebrow">Details</h3>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Client</dt><dd class="min-w-0 truncate text-right"><a href="{{ route('clients.show', $site->client) }}" wire:navigate class="cr-link">{{ $site->client->name }}</a></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">CMS</dt><dd class="text-ink">{{ $site->cms_type ? ucfirst($site->cms_type) : 'Unknown' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Environment</dt><dd class="text-ink capitalize">{{ $site->environment }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Timezone</dt><dd class="text-ink">{{ $site->timezone }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Status</dt><dd>@if ($site->is_active) <x-badge variant="ok">Active</x-badge> @else <x-badge variant="neutral">Inactive</x-badge> @endif</dd></div>
                </dl>
                @can('manage-sites')
                    <a href="{{ route('sites.edit', $site) }}" wire:navigate class="cr-link mt-3 inline-block text-xs">Edit details</a>
                @endcan
            </div>

            @can('manage-sites')
                <div class="cr-card px-5 py-4">
                    <h3 class="cr-eyebrow">Danger zone</h3>
                    <p class="mt-2 text-sm text-muted">Deleting a site removes its integrations, data and reports.</p>
                    <x-confirm-button action="delete" title="Delete {{ $site->name }}?" message="Its {{ $summary['connected'] }} {{ Str::plural('integration', $summary['connected']) }}, collected data and {{ $reportCount }} {{ Str::plural('report', $reportCount) }} are removed. This cannot be undone." confirm="Delete site" :danger="true" class="cr-btn cr-btn-danger mt-3">Delete site</x-confirm-button>
                </div>
            @endcan
        </div>
    </div>

    @can('manage-integrations')
        <x-dialog name="add-integration" title="Add an integration" size="lg" description="Choose a service to connect to {{ $site->name }}.">
            @if ($available === [])
                <p class="text-sm text-muted">Every available service is already connected — here or once for the whole workspace.</p>
            @else
                <div x-data="{ q: '' }">
                    <label for="add-integration-search" class="sr-only">Filter services</label>
                    <input type="search" id="add-integration-search" x-model="q" placeholder="Filter services…" class="cr-input mb-4" autofocus>
                    <div class="space-y-4">
                        @foreach ($available as $group)
                            <div x-show="{{ Js::from(collect($group['items'])->map(fn ($i) => strtolower($i->manifest()->name))->all()) }}.some(n => n.includes(q.toLowerCase()))">
                                <p class="cr-eyebrow mb-1.5">{{ $group['label'] }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($group['items'] as $integration)
                                        @php $m = $integration->manifest(); @endphp
                                        <a href="{{ route('sites.integrations.connect', ['site' => $site, 'key' => $m->key]) }}" wire:navigate
                                           wire:key="avail-{{ $m->key }}" x-show="{{ Js::from(strtolower($m->name)) }}.includes(q.toLowerCase())"
                                           class="flex items-center gap-3 rounded-lg border border-line px-3 py-2 text-sm transition hover:border-line-strong hover:bg-paper">
                                            <x-avatar :name="$m->name" :icon="$m->iconUrl()" aria-hidden="true" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-medium text-ink">{{ $m->name }}</span>
                                                <span class="block truncate text-xs text-faint">{{ $m->description }}</span>
                                            </span>
                                            <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-dialog>
    @endcan
</div>
