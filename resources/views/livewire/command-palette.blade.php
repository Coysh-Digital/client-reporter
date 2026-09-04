<div
    x-data="{
        open: false,
        active: 0,
        show() {
            this.open = true;
            this.active = 0;
            this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
        },
        hide() { this.open = false; },
        items() { return Array.from(this.$root.querySelectorAll('[data-result]')); },
        move(dir) {
            const n = this.items().length;
            if (!n) return;
            this.active = (this.active + dir + n) % n;
            this.items().forEach((el, i) => el.classList.toggle('bg-paper', i === this.active));
        },
        choose() {
            const el = this.items()[this.active];
            if (el) el.click();
        },
    }"
    x-on:open-command-palette.window="show()"
    x-on:keydown.window.cmd.k.prevent="show()"
    x-on:keydown.window.ctrl.k.prevent="show()"
    x-on:keydown.escape.window="hide()"
>
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]"
         x-on:keydown.down.prevent="move(1)"
         x-on:keydown.up.prevent="move(-1)"
         x-on:keydown.enter.prevent="choose()">
        <div class="fixed inset-0 bg-ink/25" x-on:click="hide()" x-transition.opacity></div>

        <div class="relative w-full max-w-xl overflow-hidden rounded-xl border border-line bg-surface shadow-2xl" x-transition>
            <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                <x-icon name="magnifying-glass" class="h-3.5 w-3.5 shrink-0 text-faint" />
                <input x-ref="input" wire:model.live.debounce.200ms="q"
                       x-on:input="active = 0"
                       type="text" placeholder="Search clients, sites and reports…"
                       class="w-full border-0 bg-transparent p-0 text-sm text-ink outline-none placeholder:text-faint">
                <span class="rounded border border-line px-1.5 py-px text-[11px] text-faint">Esc</span>
            </div>

            <div class="max-h-[46vh] overflow-y-auto py-1">
                @php $results = $this->results; @endphp
                @if (mb_strlen(trim($q)) < 2)
                    <p class="px-4 py-6 text-center text-sm text-faint">Type at least two characters to search.</p>
                @elseif (empty($results))
                    <p class="px-4 py-6 text-center text-sm text-faint">No matches for “{{ trim($q) }}”.</p>
                @else
                    @php
                        $groupIcons = ['Clients' => 'building-user', 'Sites' => 'globe', 'Reports' => 'file-chart-column'];
                    @endphp
                    @foreach ($results as $group)
                        <p class="px-4 pb-1 pt-3 text-[10.5px] font-bold uppercase tracking-[0.08em] text-faint">{{ $group['group'] }}</p>
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" wire:navigate data-result
                               x-on:click="hide()"
                               class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-paper">
                                <x-icon :name="$groupIcons[$group['group']] ?? 'file-chart-column'" class="h-4 w-4 shrink-0 text-faint" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ $item['label'] }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $item['sub'] }}</span>
                                </span>
                                <x-icon name="arrow-right" class="h-3 w-3 shrink-0 text-faint" />
                            </a>
                        @endforeach
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
