<x-layouts.guest title="You can’t open that">
    <div class="cr-card px-6 py-8 text-center">
        <p class="cr-eyebrow">Error 403</p>
        <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">You can’t open that</h1>
        <p class="mt-2 text-sm text-muted">Your account doesn’t have access to this page. If you think it should, ask an administrator.</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ url('/') }}" class="cr-btn cr-btn-primary">Go to the dashboard</a>
        </div>
    </div>
</x-layouts.guest>
