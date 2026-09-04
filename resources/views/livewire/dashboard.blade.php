<div>
    @if ($update['update_available'] ?? false)
        <div class="mb-6 flex items-center justify-between rounded-lg bg-info-soft px-4 py-3 text-sm text-info">
            <span>Client Reporter {{ $update['latest'] }} is available (you're on {{ $update['current'] }}).</span>
            @if ($update['url'] ?? null)
                <a href="{{ $update['url'] }}" target="_blank" rel="noopener" class="font-medium underline">Release notes &amp; upgrade</a>
            @endif
        </div>
    @endif

    @php
        $portfolio = $data['portfolio'];
        $needs = $data['needsAttention'];
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $needCount = count($needs);
    @endphp

    {{-- Greeting --}}
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[12.5px] font-semibold uppercase tracking-wide text-faint">{{ now()->isoFormat('dddd, D MMMM YYYY') }}</div>
            <h1 class="mt-1.5 font-serif text-3xl font-semibold tracking-tight text-ink">{{ $greeting }}, {{ auth()->user()->name }}</h1>
            <p class="mt-1.5 text-sm text-muted">
                {{ $portfolio['sitesHealthy'] }} of {{ $portfolio['sitesTotal'] }} {{ Str::plural('site', $portfolio['sitesTotal']) }} healthy.
                @if ($needCount > 0)
                    {{ $needCount }} {{ Str::plural('thing', $needCount) }} {{ $needCount === 1 ? 'needs' : 'need' }} a look.
                @else
                    Everything looks in order.
                @endif
            </p>
        </div>
        <div class="flex gap-0.5 rounded-lg border border-line bg-surface p-0.5">
            @foreach (['this_month' => 'This month', 'last_30_days' => 'Last 30 days'] as $key => $label)
                <button type="button" wire:click="setPeriod('{{ $key }}')"
                        @class([
                            'rounded-md px-3 py-1.5 text-[13px] font-medium transition',
                            'bg-accent text-white' => $period === $key,
                            'text-muted hover:text-ink' => $period !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Portfolio metric row --}}
    @php
        $split = $portfolio['healthSplit'];
        $splitTotal = max(1, $split['ok'] + $split['warn'] + $split['danger']);
    @endphp
    <div class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-line bg-surface lg:grid-cols-4">
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Clients</div>
            <div class="tnum mt-2 font-serif text-3xl font-semibold text-ink">{{ $portfolio['clients'] }}</div>
            <div class="mt-1 text-[12.5px] text-faint">across {{ $portfolio['sitesTotal'] }} {{ Str::plural('website', $portfolio['sitesTotal']) }}</div>
        </div>
        <div class="border-b border-line px-5 py-4 lg:border-b-0 lg:border-r">
            <div class="cr-eyebrow">Sites healthy</div>
            <div class="tnum mt-2 font-serif text-3xl font-semibold text-ink">{{ $portfolio['sitesHealthy'] }}<span class="text-lg font-medium text-faint"> / {{ $portfolio['sitesTotal'] }}</span></div>
            <div class="mt-2 flex gap-0.5">
                <span class="h-1.5 rounded" style="flex:{{ max($split['ok'], 0.001) }};background:var(--color-ok);"></span>
                @if ($split['warn'] > 0)<span class="h-1.5 rounded" style="flex:{{ $split['warn'] }};background:var(--color-warn);"></span>@endif
                @if ($split['danger'] > 0)<span class="h-1.5 rounded" style="flex:{{ $split['danger'] }};background:var(--color-danger);"></span>@endif
            </div>
        </div>
        <div class="border-r border-line px-5 py-4">
            <div class="cr-eyebrow">Integrations</div>
            <div class="tnum mt-2 font-serif text-3xl font-semibold text-ink">{{ $portfolio['integrations'] }}</div>
            @if ($portfolio['integrationsNeedReconnect'] > 0)
                <div class="mt-1 text-[12.5px]" style="color:var(--color-danger);">{{ $portfolio['integrationsNeedReconnect'] }} need reconnecting</div>
            @else
                <div class="mt-1 text-[12.5px] text-faint">All connected</div>
            @endif
        </div>
        <div class="px-5 py-4">
            <div class="cr-eyebrow">Reports to send</div>
            <div class="tnum mt-2 font-serif text-3xl font-semibold text-ink">{{ $portfolio['reportsToPrepare'] }}</div>
            @if ($portfolio['sitesScheduled'] > 0)
                <div class="mt-1 text-[12.5px] text-faint">{{ $portfolio['sitesScheduled'] }} {{ Str::plural('site', $portfolio['sitesScheduled']) }} on a schedule</div>
            @else
                <div class="mt-1 text-[12.5px] text-faint">No sites scheduled</div>
            @endif
        </div>
    </div>

    {{-- Needs attention --}}
    @if ($needCount > 0)
        <section class="mb-6 overflow-hidden rounded-xl border" style="border-color:#e7ddc9;background:#fdfbf7;">
            <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid #efe6d3;">
                <div class="flex items-center gap-2.5">
                    <span style="width:7px;height:7px;border-radius:999px;background:var(--color-warn);box-shadow:0 0 0 3px #f7efe1;"></span>
                    <h2 class="text-[13px] font-bold uppercase tracking-wide" style="color:var(--color-secondary);">Needs attention</h2>
                    <span class="tnum cr-badge" style="background:var(--color-warn-soft);color:var(--color-warn);">{{ $needCount }}</span>
                </div>
            </div>
            <div>
                @foreach ($needs as $item)
                    <div class="flex items-center gap-3.5 px-5 py-3.5" @if (! $loop->last) style="border-bottom:1px solid #f2ede3;" @endif>
                        <x-status-dot :variant="$item['variant']" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-ink">{{ $item['title'] }}</div>
                            <div class="truncate text-[12.5px] text-faint">{{ $item['subtitle'] }}</div>
                        </div>
                        @if ($item['when'])
                            <span class="hidden whitespace-nowrap text-xs text-faint sm:inline">{{ $item['when'] }}</span>
                        @endif
                        <a href="{{ $item['actionUrl'] }}" wire:navigate class="whitespace-nowrap text-[12.5px] font-semibold" style="color:var(--color-accent);">{{ $item['actionLabel'] }} →</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Reports this period + Notable changes --}}
    <div class="mb-8 grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
        <section class="cr-panel">
            <div class="flex items-baseline justify-between border-b border-line px-5 py-3.5">
                <h2 class="font-serif text-base font-semibold text-ink">Scheduled reports</h2>
                <span class="text-xs text-faint">Auto-generated</span>
            </div>
            @if (empty($data['reportsThisPeriod']))
                <p class="px-5 py-8 text-center text-sm text-faint">No scheduled reports yet.</p>
            @else
                <div>
                    @foreach ($data['reportsThisPeriod'] as $row)
                        <div class="flex items-center gap-3 px-5 py-3" @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                            <span class="flex-1 truncate text-[13.5px] font-semibold text-ink">{{ $row['client'] }}</span>
                            <x-status-dot :variant="$row['status']->badge()" :label="$row['status']->label()" />
                            <a href="{{ $row['actionUrl'] }}" wire:navigate class="text-xs font-semibold" style="color:var(--color-accent);">{{ $row['status']->actionLabel() }}</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5">
                <h2 class="font-serif text-base font-semibold text-ink">Notable changes</h2>
            </div>
            @if (empty($data['notableChanges']))
                <p class="px-5 py-8 text-center text-sm text-faint">No comparable data yet this period.</p>
            @else
                <div>
                    @foreach ($data['notableChanges'] as $row)
                        <div class="flex items-center justify-between gap-3 px-5 py-3" @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                            <span class="min-w-0">
                                <span class="block truncate text-[13.5px] font-semibold text-ink">{{ $row['site'] }}</span>
                                <span class="text-xs text-faint">{{ $row['metricLabel'] }}</span>
                            </span>
                            <span class="tnum whitespace-nowrap text-[13.5px] font-bold" style="color:var(--color-{{ $row['variant'] }});">{{ $row['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Recent activity --}}
    @if (! empty($activity))
        <div>
            <div class="cr-eyebrow mb-3">Recent activity</div>
            <div class="flex flex-col">
                @foreach ($activity as $event)
                    <div class="flex items-center gap-3.5 py-2">
                        <span class="tnum w-24 shrink-0 text-xs text-faint">{{ $event['when']->diffForHumans(null, true) }} ago</span>
                        <x-status-dot :variant="$event['variant']" />
                        <span class="text-[13.5px]" style="color:#4b473f;">
                            {{ $event['label'] }}
                            @if ($event['entity'])
                                @if ($event['entityUrl'])
                                    <a href="{{ $event['entityUrl'] }}" wire:navigate class="font-semibold text-ink hover:underline">{{ $event['entity'] }}</a>
                                @else
                                    <span class="font-semibold text-ink">{{ $event['entity'] }}</span>
                                @endif
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
