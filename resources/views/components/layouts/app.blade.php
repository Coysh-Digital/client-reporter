@php
    $appName = config('client-reporter.name', 'Client Reporter');
    $agencyName = app(\App\Support\Branding\BrandingResolver::class)->global()->agency_name ?: $appName;

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
            ['label' => 'Activity', 'route' => 'activity.index', 'match' => 'activity', 'icon' => 'bolt'],
            ['label' => 'Branding', 'route' => 'branding.edit', 'match' => 'branding', 'icon' => 'palette'],
        ]],
        ['heading' => 'Workspace', 'items' => [
            ['label' => 'Users', 'route' => 'users.index', 'match' => 'users', 'icon' => 'user-group'],
            ['label' => 'Settings', 'route' => 'settings.edit', 'match' => 'settings', 'icon' => 'gear'],
        ]],
    ];

    $isActive = fn (string $match): bool => request()->routeIs($match.'.*') || request()->routeIs($match);

    // Section label for the topbar, derived from the active nav item.
    $section = 'Overview';
    foreach ($groups as $g) {
        foreach ($g['items'] as $it) {
            if ($isActive($it['match'])) {
                $section = $it['label'];
            }
        }
    }

    $pageTitle = isset($title) && $title !== '' ? $title.' · '.$appName : $appName;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @include('partials.favicon')
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-paper text-ink antialiased">
    <a href="#main" class="cr-skip-link">Skip to content</a>

    <div x-data="crShell()" x-on:keydown.escape.window="closeMobile()" class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="mobileNav" x-cloak x-on:click="closeMobile()"
             class="fixed inset-0 z-30 bg-ink/30 lg:hidden" x-transition.opacity aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside x-cloak
               x-bind:class="{ 'flex': mobileNav, 'hidden': !mobileNav, 'lg:w-16': collapsed, 'lg:w-64': !collapsed }"
               class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 flex-col border-r border-line bg-surface transition-[width] lg:sticky lg:top-0 lg:flex lg:h-screen"
               x-bind:aria-label="collapsed ? 'Sidebar (collapsed)' : 'Sidebar'">
            {{-- Brand + agency --}}
            <div class="px-3 pb-3 pt-4" x-bind:class="collapsed ? 'lg:px-2' : 'lg:px-5'">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3 px-2" x-bind:class="collapsed && 'lg:px-0'">
                        <x-app-icon class="h-8 w-8 shrink-0" />
                        <span class="truncate font-serif text-base font-semibold tracking-tight text-ink" x-show="!collapsed">Client Reporter</span>
                    </a>
                    <button type="button" x-ref="mobileClose" x-on:click="closeMobile()" class="cr-btn-icon lg:hidden" aria-label="Close navigation">
                        <x-icon name="x-mark" class="h-4 w-4" />
                    </button>
                </div>

                @if (Route::has('branding.edit'))
                    <a href="{{ route('branding.edit') }}" wire:navigate x-show="!collapsed"
                       class="mt-4 flex w-full items-center justify-between gap-2 rounded-lg border border-line bg-surface px-2.5 py-2 text-left transition hover:border-line-strong">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-avatar :name="$agencyName" size="sm" />
                            <span class="truncate text-sm font-semibold text-ink">{{ $agencyName }}</span>
                        </span>
                        <span class="text-2xs text-faint">Branding</span>
                    </a>
                @else
                    <div class="mt-4 flex w-full items-center gap-2 rounded-lg border border-line px-2.5 py-2" x-show="!collapsed">
                        <x-avatar :name="$agencyName" size="sm" />
                        <span class="truncate text-sm font-semibold text-ink">{{ $agencyName }}</span>
                    </div>
                @endif
            </div>

            {{-- Nav --}}
            <nav aria-label="Main" class="flex-1 overflow-y-auto px-3 pb-3 text-sm" x-bind:class="collapsed && 'lg:px-2'">
                @foreach ($groups as $group)
                    @if ($group['heading'])
                        <p class="cr-eyebrow px-2.5 pb-1.5 pt-4" x-show="!collapsed">{{ $group['heading'] }}</p>
                        <div class="mt-3 border-t border-line" x-show="collapsed" x-cloak aria-hidden="true"></div>
                    @endif
                    @foreach ($group['items'] as $item)
                        @php $active = $isActive($item['match']); @endphp
                        @if (Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" wire:navigate x-on:click="closeMobile()"
                               class="cr-nav-item" @if ($active) aria-current="page" @endif
                               x-bind:class="collapsed && 'lg:justify-center lg:px-0'"
                               x-bind:title="collapsed ? @js($item['label']) : null">
                                <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 {{ $active ? 'text-ink' : 'text-faint' }}" />
                                <span class="ml-2.5" x-show="!collapsed">{{ $item['label'] }}</span>
                                <span class="sr-only" x-show="collapsed">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                @endforeach
            </nav>

            {{-- Queue monitor --}}
            @can('manage-integrations')
                <div class="border-t border-line px-3 py-2" x-show="!collapsed">
                    <livewire:activity.queue-status />
                </div>
            @endcan

            {{-- User menu --}}
            @auth
                <div class="border-t border-line p-2">
                    <x-dropdown direction="up" align="left" width="w-56" class="w-full">
                        <x-slot:trigger>
                            <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-paper"
                                    x-bind:class="collapsed && 'lg:justify-center lg:px-0'">
                                <x-avatar :name="auth()->user()->name" shape="circle" />
                                <span class="min-w-0 flex-1 leading-tight" x-show="!collapsed">
                                    <span class="block truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</span>
                                    <span class="block truncate text-2xs text-faint">{{ auth()->user()->role->label() }}</span>
                                </span>
                                <span class="sr-only">Account menu</span>
                                <x-icon name="chevron-up" class="h-3.5 w-3.5 text-faint" x-show="!collapsed" />
                            </button>
                        </x-slot:trigger>

                        <p class="cr-menu-heading">{{ auth()->user()->email }}</p>
                        @if (Route::has('settings.profile'))
                            <x-dropdown-item :href="route('settings.profile')" icon="user-circle">Your profile</x-dropdown-item>
                        @endif
                        <x-dropdown-item :href="route('settings.two-factor')" icon="shield-check">
                            Security
                            @if (auth()->user()->hasTwoFactorEnabled())
                                <span class="ml-auto text-2xs text-ok">2FA on</span>
                            @endif
                        </x-dropdown-item>
                        <div class="cr-menu-separator"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem" class="cr-menu-item">
                                <x-icon name="arrow-right-start-on-rectangle" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                Sign out
                            </button>
                        </form>
                    </x-dropdown>
                </div>
            @endauth

            {{-- Collapse (desktop) --}}
            <div class="hidden border-t border-line p-2 lg:block">
                <button type="button" x-on:click="toggleCollapsed()" class="cr-btn-icon w-full"
                        x-bind:aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" x-bind:title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                    <x-icon name="chevron-double-left" class="h-4 w-4 transition-transform" x-bind:class="collapsed && 'rotate-180'" />
                </button>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Topbar --}}
            <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-line px-5 lg:px-10"
                    style="height:60px;background:color-mix(in srgb, var(--color-paper) 86%, transparent);backdrop-filter:blur(8px);">
                <div class="flex items-center gap-3">
                    <button type="button" x-ref="mobileOpen" x-on:click="openMobile()" class="cr-btn-icon -ml-2 lg:hidden" aria-label="Open navigation">
                        <x-icon name="bars" class="h-5 w-5" />
                    </button>
                    <span class="text-sm text-faint">{{ $section }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('open-command-palette'))"
                            class="flex items-center gap-2 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm text-faint transition hover:border-line-strong">
                        <x-icon name="magnifying-glass" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Search…</span>
                        <x-kbd class="hidden sm:inline">⌘K</x-kbd>
                    </button>
                    @if (Route::has('reports.create'))
                        @can('manage-reports')
                            <x-button variant="primary" :href="route('reports.create')" icon="plus">New report</x-button>
                        @endcan
                    @endif
                </div>
            </header>

            <main id="main" class="flex-1 px-5 py-8 lg:px-10" tabindex="-1">
                <div class="mx-auto max-w-6xl">
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <x-confirm-dialog />
    @livewire('command-palette')
    @stack('scripts')
</body>
</html>
