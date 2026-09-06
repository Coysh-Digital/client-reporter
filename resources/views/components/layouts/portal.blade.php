@props(['title' => null])

@php
    /** @var \App\Support\Branding\ResolvedBranding $branding */
    $resolver = app(\App\Support\Branding\BrandingResolver::class);
    $portalClient = auth()->user()?->client;
    $branding = $portalClient ? $resolver->forClient($portalClient) : $resolver->resolve([$resolver->global()]);
    $fontUrl = \App\Support\GoogleFonts::googleUrl($branding->fontFamilies());
    $pageTitle = ($title ? $title.' · ' : '').$branding->agencyName;
    $nav = [
        ['label' => 'Reports', 'route' => 'portal.dashboard', 'active' => request()->routeIs('portal.*')],
        ['label' => 'Profile', 'route' => 'settings.profile', 'active' => request()->routeIs('settings.profile')],
        ['label' => 'Security', 'route' => 'settings.two-factor', 'active' => request()->routeIs('settings.two-factor')],
    ];
    $contact = array_filter([$branding->website, $branding->email, $branding->phone, $branding->address]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    {{-- White-labelled: only ever the agency's own favicon here. --}}
    @if ($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    {{ Vite::fonts() }}
    @if ($fontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fontUrl }}" rel="stylesheet">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --brand-primary: {{ $branding->primaryColor }}; --brand-secondary: {{ $branding->secondaryColor }}; --color-accent: {{ $branding->primaryColor }}; }
        .portal-heading { font-family: {!! $branding->headingFontStack() !!}; }
    </style>
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <a href="#main" class="cr-skip-link">Skip to content</a>

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-4xl items-center justify-between gap-4 px-4">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                @if ($branding->hasLogo())
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" class="h-8">
                @else
                    <span class="portal-heading truncate text-lg font-semibold" style="color: var(--brand-primary);">{{ $branding->agencyName }}</span>
                @endif
            </a>
            <nav aria-label="Main" class="flex items-center gap-1 text-sm">
                @foreach ($nav as $item)
                    @if (Route::has($item['route']))
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @class(['whitespace-nowrap rounded-md px-2.5 py-1.5 transition', 'font-semibold text-ink' => $item['active'], 'text-muted hover:text-ink' => ! $item['active']])
                           @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endif
                @endforeach
                <form method="POST" action="{{ route('logout') }}" class="ml-2">
                    @csrf
                    <button type="submit" class="cr-btn cr-btn-ghost cr-btn-sm">Sign out</button>
                </form>
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-4xl px-4 py-10" tabindex="-1">
        <x-flash />
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-4xl px-4 pb-10 pt-4 text-center text-xs text-faint">
        <p class="font-semibold text-muted">{{ $branding->agencyName }}</p>
        @if ($branding->tagline)
            <p class="mt-0.5">{{ $branding->tagline }}</p>
        @endif
        @if ($contact !== [])
            <p class="mt-2 flex flex-wrap justify-center gap-x-3 gap-y-1">
                @if ($branding->website)<a href="{{ $branding->website }}" class="hover:text-ink" rel="noopener">{{ preg_replace('#^https?://#', '', $branding->website) }}</a>@endif
                @if ($branding->email)<a href="mailto:{{ $branding->email }}" class="hover:text-ink">{{ $branding->email }}</a>@endif
                @if ($branding->phone)<a href="tel:{{ preg_replace('/\s+/', '', $branding->phone) }}" class="hover:text-ink">{{ $branding->phone }}</a>@endif
                @if ($branding->address)<span>{{ $branding->address }}</span>@endif
            </p>
        @endif
    </footer>

    <x-confirm-dialog />
</body>
</html>
