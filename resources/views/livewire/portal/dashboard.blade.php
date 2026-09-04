<div>
    <h1 class="font-serif text-2xl font-semibold tracking-tight text-ink">Welcome, {{ $client->name }}</h1>
    <p class="mt-1 text-sm text-muted">Your website reports and details.</p>

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-ink">Reports</h2>
        @if ($reports->isEmpty())
            <x-empty-state title="No reports yet" description="Your reports will appear here as they're published." />
        @else
            <div class="cr-card divide-y divide-line">
                @foreach ($reports as $report)
                    <a href="{{ route('portal.report', $report) }}" wire:key="preport-{{ $report->id }}"
                       class="flex items-center justify-between px-5 py-4 hover:bg-paper">
                        <div>
                            <div class="font-medium text-ink">{{ $report->title }}</div>
                            <div class="text-xs text-muted">{{ $report->site->name }} · {{ $report->dateRange()->label() }}</div>
                        </div>
                        <span class="text-sm" style="color: var(--brand-primary);">View →</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($sites->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold text-ink">Your websites</h2>
            <div class="cr-card divide-y divide-line">
                @foreach ($sites as $site)
                    <div wire:key="psite-{{ $site->id }}" class="flex items-center justify-between px-5 py-3">
                        <span class="text-ink">{{ $site->name }}</span>
                        <a href="{{ $site->url }}" target="_blank" rel="noopener" class="text-xs text-muted hover:text-ink">{{ $site->host() }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
