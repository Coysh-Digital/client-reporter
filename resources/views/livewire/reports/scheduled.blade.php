<div>
    <x-page-header title="Scheduled reports" subtitle="Sites that generate a report automatically once each period closes." eyebrow="Reports">
        <x-slot:actions>
            <x-button :href="route('reports.index')" icon="chevron-left" variant="ghost" wire:navigate>All reports</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 cr-card px-5 py-4 text-sm text-muted">
        A report is built automatically the day after each period ends, and lands in
        <a href="{{ route('reports.index') }}" wire:navigate class="cr-link">Reports</a> as a draft for you to review and send. Set or change a site's schedule on its
        <span class="text-ink">Edit site</span> page, under <span class="text-ink">Reporting schedule</span>.
    </div>

    @if (empty($sites))
        <x-empty-state icon="arrow-path" title="No sites are scheduled yet"
            description="Turn on a weekly, monthly or quarterly schedule on any site to have its reports generate automatically.">
            <x-slot:action>
                <x-button variant="primary" :href="route('sites.index')" icon="globe" wire:navigate>Go to sites</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-table caption="Scheduled reports">
            <thead>
                <tr>
                    <x-th>Site</x-th>
                    <x-th>Frequency</x-th>
                    <x-th>Sections</x-th>
                    <x-th>Next report</x-th>
                    <x-th>Last generated</x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sites as $row)
                    @php($site = $row['site'])
                    <tr wire:key="scheduled-{{ $site->id }}">
                        <x-td>
                            <a href="{{ route('sites.show', $site) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                                <x-avatar :name="$site->client->name" size="lg" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block truncate text-md font-semibold text-ink">{{ $site->name }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $site->client->name }} · {{ $site->host() }}</span>
                                </span>
                            </a>
                        </x-td>
                        <x-td nowrap><x-badge variant="info">{{ $row['frequency'] }}</x-badge></x-td>
                        <x-td nowrap><span class="text-muted">{{ $row['template'] ?? 'Default sections' }}</span></x-td>
                        <x-td nowrap>
                            @if ($row['next'])
                                <span class="tnum text-ink">{{ $row['next']->format('j M Y') }}</span>
                                <span class="block text-xs text-faint">{{ $row['next']->diffForHumans() }}</span>
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </x-td>
                        <x-td nowrap>
                            @if ($row['lastReport'])
                                <a href="{{ route('reports.show', $row['lastReport']) }}" wire:navigate class="cr-link text-xs">
                                    {{ $row['lastReport']->generated_at?->format('j M Y') ?? 'In progress' }}
                                </a>
                            @else
                                <span class="text-xs text-faint">None yet</span>
                            @endif
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</div>
