<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('client-reporter.name', 'Client Reporter') }}</title>
    @include('partials.favicon')
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-paper text-ink antialiased">
    @php
        // Grouped navigation. Each item's active state is matched on the route
        // prefix so nested pages keep the section highlighted.
        $groups = [
            ['heading' => null, 'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'gauge-high'],
            ]],
            ['heading' => 'Portfolio', 'items' => [
                ['label' => 'Clients', 'route' => 'clients.index', 'match' => 'clients', 'icon' => 'building-user'],
                ['label' => 'Sites', 'route' => 'sites.index', 'match' => 'sites', 'icon' => 'globe'],
                ['label' => 'Reports', 'route' => 'reports.index', 'match' => 'reports', 'icon' => 'file-chart-column'],
                ['label' => 'Templates', 'route' => 'templates.index', 'match' => 'templates', 'icon' => 'layer-group'],
            ]],
            ['heading' => 'Setup', 'items' => [
                ['label' => 'Integrations', 'route' => 'integrations.index', 'match' => 'integrations', 'icon' => 'plug'],
                ['label' => 'Branding', 'route' => 'branding.edit', 'match' => 'branding', 'icon' => 'palette'],
            ]],
            ['heading' => 'Workspace', 'items' => [
                ['label' => 'Users', 'route' => 'users.index', 'match' => 'users', 'icon' => 'user-group'],
                ['label' => 'Settings', 'route' => 'settings.edit', 'match' => 'settings', 'icon' => 'gear'],
            ]],
        ];

        $isActive = fn (string $match): bool => request()->routeIs($match . '.*') || request()->routeIs($match);

        // Section label for the topbar, derived from the active nav item.
        $section = 'Overview';
        foreach ($groups as $g) {
            foreach ($g['items'] as $it) {
                if ($isActive($it['match'])) {
                    $section = $it['label'];
                }
            }
        }

        $agencyName = app(\App\Support\Branding\BrandingResolver::class)->global()->agency_name
            ?: config('client-reporter.name', 'Client Reporter');
    @endphp

    <div x-data="{ mobileNav: false }" class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="mobileNav" x-cloak @click="mobileNav = false"
             class="fixed inset-0 z-30 bg-ink/30 lg:hidden" x-transition.opacity></div>

        {{-- Sidebar --}}
        <aside :class="mobileNav ? 'flex' : 'hidden'"
               class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 flex-col border-r border-line bg-surface lg:sticky lg:top-0 lg:flex lg:h-screen">
            {{-- Brand + agency --}}
            <div class="px-5 pb-4 pt-5">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                    <x-app-icon class="h-8 w-8" />
                    <span class="font-serif text-base font-semibold tracking-tight text-ink">Client Reporter</span>
                </a>

                @if (Route::has('branding.edit'))
                    <a href="{{ route('branding.edit') }}" wire:navigate
                       class="mt-4 flex w-full items-center justify-between gap-2 rounded-lg border border-line bg-surface px-2.5 py-2 text-left transition hover:border-line-strong">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-avatar :name="$agencyName" size="sm" />
                            <span class="truncate text-[13px] font-semibold text-ink">{{ $agencyName }}</span>
                        </span>
                        <span class="text-[10px] text-faint">Branding</span>
                    </a>
                @else
                    <div class="mt-4 flex w-full items-center gap-2 rounded-lg border border-line px-2.5 py-2">
                        <x-avatar :name="$agencyName" size="sm" />
                        <span class="truncate text-[13px] font-semibold text-ink">{{ $agencyName }}</span>
                    </div>
                @endif
            </div>

            {{-- Nav --}}
            <nav class="flex-1 overflow-y-auto px-3 pb-3 text-sm">
                @foreach ($groups as $group)
                    @if ($group['heading'])
                        <p class="px-2.5 pb-1.5 pt-4 text-[10.5px] font-bold uppercase tracking-[0.09em] text-faint">{{ $group['heading'] }}</p>
                    @endif
                    @foreach ($group['items'] as $item)
                        @php $active = $isActive($item['match']); @endphp
                        @if (Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" wire:navigate @click="mobileNav = false"
                               @class([
                                   'relative mt-0.5 flex items-center rounded-lg px-3 py-2 font-medium transition',
                                   'bg-surface text-ink shadow-sm ring-1 ring-line' => $active,
                                   'text-muted hover:bg-paper hover:text-ink' => ! $active,
                               ])>
                                @if ($active)
                                    <span class="absolute -left-px top-1/2 h-4 w-[3px] -translate-y-1/2 rounded" style="background:var(--color-accent);"></span>
                                @endif
                                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 {{ $active ? 'text-ink' : 'text-faint' }}" />
                                <span class="ml-2.5">{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="mt-0.5 flex cursor-default items-center rounded-lg px-3 py-2 font-medium text-faint">{{ $item['label'] }}</span>
                        @endif
                    @endforeach
                @endforeach
            </nav>

            {{-- User --}}
            @auth
                <div class="border-t border-line p-3">
                    <div class="flex items-center gap-2.5 px-1.5 py-1">
                        <x-avatar :name="auth()->user()->name" shape="circle" />
                        <div class="min-w-0 leading-tight">
                            <p class="truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11.5px] text-faint">{{ auth()->user()->role->label() }}</p>
                        </div>
                        @if (Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                                @csrf
                                <button type="submit" class="text-xs text-muted hover:text-ink">Sign out</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endauth
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex h-15 items-center justify-between gap-4 border-b border-line px-5 lg:px-10"
                    style="height:60px;background:color-mix(in srgb, var(--color-paper) 86%, transparent);backdrop-filter:blur(8px);">
                <div class="flex items-center gap-3">
                    <button type="button" @click="mobileNav = true" class="text-muted hover:text-ink lg:hidden" aria-label="Open navigation">
                        <x-icon name="bars" class="h-5 w-5" />
                    </button>
                    <span class="text-sm text-faint">{{ $section }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('open-command-palette'))"
                            class="flex items-center gap-2 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-faint transition hover:border-line-strong">
                        <x-icon name="magnifying-glass" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Search…</span>
                        <span class="hidden rounded border border-line px-1.5 py-px text-[11px] text-faint sm:inline">⌘K</span>
                    </button>
                    @if (Route::has('reports.create'))
                        @can('manage-reports')
                            <a href="{{ route('reports.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                                <x-icon name="plus" class="h-3.5 w-3.5" />
                                New report
                            </a>
                        @endcan
                    @endif
                </div>
            </header>

            <main class="flex-1 px-5 py-8 lg:px-10">
                <div class="mx-auto max-w-6xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewire('command-palette')
    @stack('scripts')
</body>
</html>
