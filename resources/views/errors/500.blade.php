<x-layouts.guest title="Something went wrong">
    <div class="cr-card px-6 py-8 text-center">
        <p class="cr-eyebrow">Error 500</p>
        <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">Something went wrong</h1>
        <p class="mt-2 text-sm text-muted">The server hit a problem. It has been logged; try again in a moment.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url('/') }}" class="cr-btn cr-btn-primary">Go to the dashboard</a>
        </div>
    </div>
</x-layouts.guest>
