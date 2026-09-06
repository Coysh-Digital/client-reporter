@props(['caption' => null])

{{-- Wide tables scroll inside their own panel; the page never scrolls sideways. --}}
<div {{ $attributes->class(['cr-panel overflow-x-auto']) }}>
    <table class="cr-table">
        @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        {{ $slot }}
    </table>
</div>
