@props(['title' => null])

@php
    /**
     * The sign-in, reset and error pages are seen by clients as well as staff,
     * so once an agency has set its branding the page carries that instead of
     * the product's own name.
     */
    $resolver = app(\App\Support\Branding\BrandingResolver::class);
    $global = $resolver->global();
    $branded = (string) $global->agency_name !== '';
    $branding = $resolver->resolve([$global]);
    $appName = config('client-reporter.name', 'Client Reporter');
    $pageTitle = ($title ? $title.' · ' : '').($branded ? $branding->agencyName : $appName);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    @if ($branded && $branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @else
        @include('partials.favicon')
    @endif
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($branded)
        <style>:root { --brand-primary: {{ $branding->primaryColor }}; --color-accent: {{ $branding->primaryColor }}; }</style>
    @endif
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <a href="#main" class="cr-skip-link">Skip to content</a>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 text-center">
            @if ($branded)
                @if ($branding->hasLogo())
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" class="mx-auto mb-3 h-10">
                @else
                    <span class="font-serif text-2xl font-semibold tracking-tight" style="color: var(--brand-primary);">{{ $branding->agencyName }}</span>
                @endif
                @if ($branding->tagline)
                    <p class="mt-1 text-sm text-muted">{{ $branding->tagline }}</p>
                @endif
            @else
                <x-app-icon class="mx-auto mb-3 h-10 w-10" />
                <span class="font-serif text-2xl font-semibold tracking-tight text-ink">{{ $appName }}</span>
                <p class="mt-1 text-sm text-muted">Self-hosted client reporting for web agencies.</p>
            @endif
        </div>
        <main id="main" class="w-full max-w-sm" tabindex="-1">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
