<div>
    <x-page-header title="Reports" subtitle="Every report you've built for your clients." eyebrow="Portfolio">
        <x-slot:actions>
            <x-button :href="route('reports.scheduled')" icon="arrow-path" variant="ghost" wire:navigate>Scheduled</x-button>
            @can('manage-reports')
                <x-button variant="primary" :href="route('reports.create')" icon="plus">New report</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="reports-search">Search reports</label>
        <input wire:model.live.debounce.300ms="search" id="reports-search" type="search" placeholder="Search reports or sites…" class="cr-input max-w-xs">
        <x-segmented :options="['all' => 'All', 'draft' => 'Draft', 'final' => 'Generated']" :value="$status" action="setStatus" label="Filter reports by status" />
        <label class="sr-only" for="reports-client">Client</label>
        <select wire:model.live="client" id="reports-client" class="cr-input w-auto py-1.5 pr-8 text-sm">
            <option value="">All clients</option>
            @foreach ($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="reports-site">Site</label>
        <select wire:model.live="site" id="reports-site" class="cr-input w-auto py-1.5 pr-8 text-sm">
            <option value="">All sites</option>
            @foreach ($sites as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($reports->isEmpty())
        @if ($search !== '' || $status !== 'all' || $site !== null || $client !== null)
            <x-empty-state icon="magnifying-glass" title="No reports match" description="Try a different search or clear the filters.">
                <x-slot:action>
                    <x-button wire:click="$set('search', ''); $set('status', 'all'); $set('site', null); $set('client', null)">Clear filters</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state icon="file-chart-column" title="No reports yet" description="Create your first report to turn connected data into a branded client report.">
                @can('manage-reports')
                    <x-slot:action>
                        <x-button variant="primary" :href="route('reports.create')" icon="plus">New report</x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    @else
        <x-table caption="Reports">
            <thead>
                <tr>
                    <x-th sort="title" :current="$this->currentSort()" :direction="$this->currentDirection()">Report</x-th>
                    <x-th sort="period" :current="$this->currentSort()" :direction="$this->currentDirection()">Period</x-th>
                    <x-th sort="status" :current="$this->currentSort()" :direction="$this->currentDirection()">Status</x-th>
                    <x-th sort="generated" :current="$this->currentSort()" :direction="$this->currentDirection()">Generated</x-th>
                    <x-th><span class="sr-only">Actions</span></x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <x-td>
                            <a href="{{ route('reports.show', $report) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                                <x-avatar :name="$report->site->client->name" size="lg" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block truncate text-md font-semibold text-ink">{{ $report->title }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $report->site->client->name }} · {{ $report->site->host() }}</span>
                                </span>
                            </a>
                        </x-td>
                        <x-td nowrap><span class="tnum text-muted">{{ $report->dateRange()->label() }}</span></x-td>
                        <x-td nowrap>
                            @if ($report->isGenerating())
                                <x-status-dot variant="info" :label="$report->generation_status->label()" />
                            @elseif ($report->generationFailed())
                                <x-status-dot variant="danger" label="Generation failed" />
                            @elseif ($report->status === 'final')
                                <x-status-dot variant="ok" label="Generated" />
                            @else
                                <x-status-dot variant="neutral" label="Draft" />
                            @endif
                        </x-td>
                        <x-td nowrap><span class="text-xs text-muted">{{ $report->generated_at?->diffForHumans() ?? '—' }}</span></x-td>
                        <x-td align="right" nowrap>
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('reports.show', $report) }}" wire:navigate class="cr-link text-xs">{{ $report->status === 'final' ? 'View' : 'Open' }}</a>
                                @can('manage-reports')
                                    <x-dropdown>
                                        <x-slot:trigger>
                                            <button type="button" class="cr-btn-icon" aria-label="Actions for {{ $report->title }}">
                                                <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                            </button>
                                        </x-slot:trigger>
                                        <x-dropdown-item :href="route('reports.edit', $report)" icon="pencil-square">Edit sections</x-dropdown-item>
                                        @if ($report->isGenerated())
                                            <x-dropdown-item :href="route('reports.pdf', $report)" icon="file-chart-column" :navigate="false">Download PDF</x-dropdown-item>
                                        @endif
                                        <div class="cr-menu-separator"></div>
                                        <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                            action="delete({{ $report->id }})"
                                            title="Delete this report?"
                                            message="“{{ $report->title }}” and any share links to it will stop working. This cannot be undone."
                                            confirm="Delete report" :danger="true">
                                            <x-icon name="trash-can" class="h-3.5 w-3.5 shrink-0" /> Delete
                                        </x-confirm-button>
                                    </x-dropdown>
                                @endcan
                            </div>
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $reports->links('vendor.pagination.cr') }}</div>
    @endif
</div>
