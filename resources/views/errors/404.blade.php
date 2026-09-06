<x-layouts.guest title="Page not found">
    <div class="cr-card px-6 py-8 text-center">
        <p class="cr-eyebrow">Error 404</p>
        <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">Page not found</h1>
        <p class="mt-2 text-sm text-muted">That page doesn’t exist, or the link has changed.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url('/') }}" class="cr-btn cr-btn-primary">Go to the dashboard</a>
        </div>
    </div>
</x-layouts.guest>
