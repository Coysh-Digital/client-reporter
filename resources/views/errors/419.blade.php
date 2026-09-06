<x-layouts.guest title="This page has expired">
    <div class="cr-card px-6 py-8 text-center">
        <p class="cr-eyebrow">Error 419</p>
        <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">This page has expired</h1>
        <p class="mt-2 text-sm text-muted">The form sat open for a while. Go back and try again.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url('/') }}" class="cr-btn cr-btn-primary">Go to the dashboard</a>
        </div>
    </div>
</x-layouts.guest>
