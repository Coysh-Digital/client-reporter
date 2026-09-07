@php
    use App\Enums\BackgroundTaskStatus;
    $active = $running + $queued;
    if ($running > 0) {
        $dot = 'var(--color-info)';
        $summary = $running === 1 ? '1 running' : $running.' running';
    } elseif ($queued > 0) {
        $dot = 'var(--color-warn)';
        $summary = $queued === 1 ? '1 queued' : $queued.' queued';
    } else {
        $dot = 'var(--color-line-strong)';
        $summary = 'Idle';
    }
@endphp
<div wire:poll.5s>
    <a href="{{ route('activity.index') }}" wire:navigate
       class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-muted transition hover:bg-paper hover:text-ink"
       title="Background activity — {{ $summary }}">
        <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $running > 0 ? 'animate-pulse' : '' }}" style="background: {{ $dot }};"></span>
        <span>Activity</span>
        <span class="ml-auto flex items-center gap-1.5 text-xs text-faint">
            <span>{{ $summary }}</span>
            @if ($failed > 0)
                <span class="rounded-full px-1.5 py-0.5 text-2xs font-semibold" style="background: var(--color-danger-soft); color: var(--color-danger);">{{ $failed }} failed</span>
            @endif
        </span>
    </a>

    @if ($tasks->isNotEmpty())
        <div class="mt-1 space-y-2 px-3 pb-1">
            @foreach ($tasks as $task)
                @php($pct = $task->percent())
                <div wire:key="cr-task-{{ $task->id }}">
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="min-w-0 truncate text-ink">{{ $task->description ?: $task->label }}</span>
                        <span class="shrink-0 text-2xs text-faint">
                            @if ($task->status === BackgroundTaskStatus::Running)
                                {{ $pct !== null ? $pct.'%' : 'running' }}
                            @else
                                queued
                            @endif
                        </span>
                    </div>
                    @if ($pct !== null)
                        <div class="mt-1 h-1 overflow-hidden rounded-full" style="background: var(--color-line);">
                            <div class="h-1 rounded-full transition-all" style="width: {{ $pct }}%; background: var(--color-info);"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
