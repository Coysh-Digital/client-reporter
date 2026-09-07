<div wire:poll.30s>
    <x-dropdown align="right" width="w-80">
        <x-slot:trigger>
            <button type="button" class="cr-btn-icon relative" aria-label="Notifications{{ $unread > 0 ? ' ('.$unread.' unread)' : '' }}">
                <x-icon name="bell" class="h-5 w-5" />
                @if ($unread > 0)
                    <span class="absolute -right-1 -top-1 inline-flex min-w-[1.05rem] items-center justify-center rounded-full px-1 text-2xs font-semibold leading-none"
                          style="height:1.05rem;background:var(--color-danger);color:#fff;">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </button>
        </x-slot:trigger>

        <div class="flex items-center justify-between px-3 py-2">
            <span class="cr-eyebrow">Notifications</span>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="cr-link text-xs">Mark all read</button>
            @endif
        </div>
        <div class="cr-menu-separator"></div>

        @forelse ($recent as $note)
            <button type="button" wire:key="note-{{ $note->id }}" wire:click="open('{{ $note->id }}')"
                    class="block w-full px-3 py-2 text-left transition hover:bg-paper">
                <span class="flex items-start gap-2">
                    <span class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $note->read_at ? 'transparent' : 'var(--color-info)' }};"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-ink">{{ $note->data['title'] ?? 'Notification' }}</span>
                        @if (! empty($note->data['body']))
                            <span class="block text-xs text-muted">{{ $note->data['body'] }}</span>
                        @endif
                        <span class="mt-0.5 block text-2xs text-faint">{{ $note->created_at->diffForHumans() }}</span>
                    </span>
                </span>
            </button>
        @empty
            <div class="px-3 py-6 text-center text-sm text-faint">You're all caught up.</div>
        @endforelse
    </x-dropdown>
</div>
