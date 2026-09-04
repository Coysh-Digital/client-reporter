@php
    $branding = app(\App\Support\Branding\BrandingResolver::class)->resolve([
        app(\App\Support\Branding\BrandingResolver::class)->global(),
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $branding->agencyName }}</title>
    @if ($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @else
        @include('partials.favicon')
    @endif
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --brand-primary: {{ $branding->primaryColor }}; }</style>
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-4xl items-center justify-between px-4">
            <div class="flex items-center gap-3">
                @if ($branding->hasLogo())
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" class="h-8">
                @else
                    <span class="font-serif text-lg font-semibold" style="color: var(--brand-primary);">{{ $branding->agencyName }}</span>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-muted hover:text-ink">Sign out</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-10">
        {{ $slot }}
    </main>
</body>
</html>
