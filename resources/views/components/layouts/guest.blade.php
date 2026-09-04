@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · ' : '' }}{{ config('client-reporter.name', 'Client Reporter') }}</title>
    @include('partials.favicon')
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 text-center">
            <x-app-icon class="mx-auto mb-3 h-10 w-10" />
            <span class="font-serif text-2xl font-semibold tracking-tight text-ink">Client Reporter</span>
            <p class="mt-1 text-sm text-muted">Self-hosted client reporting for web agencies.</p>
        </div>
        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
