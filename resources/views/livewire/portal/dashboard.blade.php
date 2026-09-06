<div>
    <h1 class="portal-heading text-2xl font-semibold tracking-tight text-ink">Welcome, {{ $client->name }}</h1>
    <p class="mt-1 text-sm text-muted">Your website reports, ready whenever you need them.</p>

    @if ($sites->isNotEmpty())
        <section class="mt-8">
            <h2 class="cr-eyebrow mb-3">Your websites</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($sites as $s)
                    @php $siteHealth = $health[$s->id] ?? null; $last = $latest->get($s->id); @endphp
                    <div wire:key="psite-{{ $s->id }}" @class(['cr-panel px-5 py-4', 'ring-2 ring-accent/30' => $site === $s->id])>
                        <div class="flex items-start gap-3">
                            <x-avatar :name="$s->name" size="lg" :icon="$s->faviconUrl()" aria-hidden="true" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-ink">{{ $s->name }}</div>
                                <a href="{{ $s->url }}" target="_blank" rel="noopener" class="block truncate text-xs text-faint hover:text-ink">{{ $s->host() }}</a>
                            </div>
                            @if ($siteHealth)
                                <x-status-dot :variant="$siteHealth->badge()" :label="$siteHealth->label()" />
                            @endif
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                            <span class="text-muted">
                                @if ($last)
                                    Latest report: {{ $last->dateRange()->label() }}
                                @else
                                    No reports yet
                                @endif
                            </span>
                            @if ($last)
                                <a href="{{ route('portal.report', $last) }}" class="cr-link">View latest</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="cr-eyebrow">Reports</h2>
            @if ($sites->count() > 1)
                <label class="flex items-center gap-2 text-xs text-muted">
                    <span>Website</span>
                    <select wire:model.live="site" class="cr-input w-auto py-1.5 pr-8 text-sm">
                        <option value="">All websites</option>
                        @foreach ($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        @if ($reports->isEmpty())
            <x-empty-state icon="file-chart-column" title="No reports yet" description="Your reports will appear here as they're published." />
        @else
            @foreach ($byYear as $year => $yearReports)
                <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-faint first:mt-0">{{ $year }}</h3>
                <div class="cr-panel divide-y divide-line">
                    @foreach ($yearReports as $report)
                        <div wire:key="preport-{{ $report->id }}" class="flex items-center justify-between gap-4 px-5 py-4">
                            <a href="{{ route('portal.report', $report) }}" class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $report->title }}</span>
                                <span class="block truncate text-xs text-muted">{{ $report->site->name }} · {{ $report->dateRange()->label() }}</span>
                            </a>
                            <div class="flex shrink-0 items-center gap-1">
                                <x-button size="sm" variant="ghost" :href="route('portal.report.pdf', $report)" :navigate="false" icon="file-chart-column">PDF</x-button>
                                <x-button size="sm" :href="route('portal.report', $report)" :navigate="false">View</x-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
            @if ($reports->hasPages())
                <div class="mt-4">{{ $reports->links('vendor.pagination.cr') }}</div>
            @endif
        @endif
    </section>
</div>
