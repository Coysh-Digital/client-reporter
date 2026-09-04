@php
    if ($running > 0) {
        $dot = 'var(--color-info)';
        $text = $running === 1 ? '1 running' : $running.' running';
    } elseif ($queued > 0) {
        $dot = 'var(--color-warn)';
        $text = $queued === 1 ? '1 queued' : $queued.' queued';
    } else {
        $dot = 'var(--color-line-strong)';
        $text = 'Idle';
    }
@endphp
<a href="{{ route('activity.index') }}" wire:navigate wire:poll.10s
   class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition hover:bg-paper hover:text-ink"
   title="Background queue — {{ $text }}">
    <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $dot }};"></span>
    <span>Queue</span>
    <span class="ml-auto flex items-center gap-1.5 text-xs text-faint">
        <span>{{ $text }}</span>
        @if ($failed > 0)
            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="background: var(--color-danger-soft); color: var(--color-danger);">{{ $failed }} failed</span>
        @endif
    </span>
</a>
