<div wire:poll.3s>
    @if ($tasks->isNotEmpty())
        <div class="cr-card mt-5 px-5 py-4">
            <h3 class="cr-eyebrow mb-3">Currently running</h3>
            <ul class="space-y-3">
                @foreach ($tasks as $task)
                    @php($pct = $task->percent())
                    <li wire:key="running-{{ $task->id }}" class="text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="min-w-0">
                                <span class="font-medium text-ink">{{ $task->label }}</span>
                                @if ($task->description)
                                    <span class="text-muted"> · {{ $task->description }}</span>
                                @endif
                            </span>
                            <span class="shrink-0">
                                <x-badge :variant="$task->status->badge()">{{ $task->status->label() }}</x-badge>
                            </span>
                        </div>
                        @if ($pct !== null)
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full" style="background: var(--color-line);">
                                    <div class="h-1.5 rounded-full transition-all" style="width: {{ $pct }}%; background: var(--color-info);"></div>
                                </div>
                                <span class="shrink-0 text-2xs text-faint tnum">{{ $pct }}%</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
