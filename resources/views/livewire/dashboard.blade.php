<div>
    @if ($update['update_available'] ?? false)
        <x-alert variant="info" class="mb-6">
            Client Reporter {{ $update['latest'] }} is available (you're on {{ $update['current'] }}).
            @if ($update['url'] ?? null)
                <x-slot:action><a href="{{ $update['url'] }}" target="_blank" rel="noopener" class="font-medium underline">Release notes &amp; upgrade</a></x-slot:action>
            @endif
        </x-alert>
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
            <div class="text-xs font-semibold uppercase tracking-wide text-faint">{{ now()->isoFormat('dddd, D MMMM YYYY') }}</div>
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
        <x-segmented :options="['this_month' => 'This month', 'last_30_days' => 'Last 30 days']" :value="$period" action="setPeriod" variant="solid" label="Dashboard period" />
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
            <div class="mt-1 text-xs text-faint">across {{ $portfolio['sitesTotal'] }} {{ Str::plural('website', $portfolio['sitesTotal']) }}</div>
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
                <div class="mt-1 text-xs" style="color:var(--color-danger);">{{ $portfolio['integrationsNeedReconnect'] }} need reconnecting</div>
            @else
                <div class="mt-1 text-xs text-faint">All connected</div>
            @endif
        </div>
        <div class="px-5 py-4">
            <div class="cr-eyebrow">Reports to send</div>
            <div class="tnum mt-2 font-serif text-3xl font-semibold text-ink">{{ $portfolio['reportsToPrepare'] }}</div>
            @if ($portfolio['sitesScheduled'] > 0)
                <div class="mt-1 text-xs text-faint">{{ $portfolio['sitesScheduled'] }} {{ Str::plural('site', $portfolio['sitesScheduled']) }} on a schedule</div>
            @else
                <div class="mt-1 text-xs text-faint">No sites scheduled</div>
            @endif
        </div>
    </div>

    {{-- Needs attention --}}
    @if ($needCount > 0)
        <section class="cr-panel mb-6 border-warn/30" style="background:color-mix(in srgb, var(--color-warn-soft) 40%, var(--color-surface));">
            <div class="cr-panel-header">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-2 w-2 rounded-full ring-4 ring-warn-soft" style="background:var(--color-warn);" aria-hidden="true"></span>
                    <h2 class="cr-eyebrow" style="color:var(--color-secondary);">Needs attention</h2>
                    <x-badge variant="warn" class="tnum">{{ $needCount }}</x-badge>
                </div>
            </div>
            <div class="divide-y divide-line">
                @foreach ($needs as $item)
                    <div class="flex items-center gap-3.5 px-5 py-3.5">
                        <x-status-dot :variant="$item['variant']" :label="ucfirst($item['variant'] === 'danger' ? 'urgent' : ($item['variant'] === 'warn' ? 'warning' : 'info'))" class="w-20 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-ink">{{ $item['title'] }}</div>
                            <div class="truncate text-xs text-faint">{{ $item['subtitle'] }}</div>
                        </div>
                        @if ($item['when'])
                            <span class="hidden whitespace-nowrap text-xs text-faint sm:inline">{{ $item['when'] }}</span>
                        @endif
                        <a href="{{ $item['actionUrl'] }}" wire:navigate class="cr-link whitespace-nowrap text-xs">{{ $item['actionLabel'] }} →</a>
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
                            <span class="flex-1 truncate text-sm font-semibold text-ink">{{ $row['client'] }}</span>
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
                                <span class="block truncate text-sm font-semibold text-ink">{{ $row['site'] }}</span>
                                <span class="text-xs text-faint">{{ $row['metricLabel'] }}</span>
                            </span>
                            <span class="tnum whitespace-nowrap text-sm font-bold" style="color:var(--color-{{ $row['variant'] }});">{{ $row['text'] }}</span>
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
                        <x-status-dot :variant="$event['variant']" :label="$event['variant'] === 'danger' ? 'Failed' : ($event['variant'] === 'ok' ? 'OK' : 'Info')" class="w-16 shrink-0" />
                        <span class="text-sm text-muted">
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
