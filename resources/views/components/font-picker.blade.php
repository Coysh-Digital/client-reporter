@props([
    'model',            // Livewire property name to set (stores a full CSS stack)
    'label',
    'current' => '',    // current stored stack value
])

@php
    $fonts = collect(\App\Support\GoogleFonts::all())
        ->map(fn ($f) => [
            'family' => $f['family'],
            'category' => $f['category'],
            'stack' => \App\Support\GoogleFonts::cssStack($f['family']),
        ])
        ->values()
        ->all();
    $currentFamily = \App\Support\GoogleFonts::extractFamily($current);
@endphp

<div class="relative"
     x-data="{
        open: false,
        q: '',
        model: @js($model),
        selected: @js($currentFamily),
        fonts: @js($fonts),
        filtered() {
            const q = this.q.toLowerCase().trim();
            return this.fonts.filter(f => !q || f.family.toLowerCase().includes(q) || f.category.includes(q));
        },
        choose(f) {
            this.selected = f.family;
            this.$wire.set(this.model, f.stack);
            this.open = false;
            this.q = '';
        },
     }">
    <label class="cr-label">{{ $label }}</label>
    <button type="button" @click="open = !open"
            class="cr-input flex items-center justify-between text-left">
        <span x-text="selected || 'Choose a Google Font…'" :class="selected ? 'text-ink' : 'text-faint'"></span>
        <span class="text-[10px] text-faint">▾</span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-line bg-surface shadow-lg">
        <div class="border-b border-line p-2">
            <input x-model="q" @click.stop type="text" placeholder="Search Google Fonts…"
                   class="cr-input text-sm" x-init="$watch('open', v => v && $nextTick(() => $el.focus()))">
        </div>
        <div class="max-h-64 overflow-y-auto py-1">
            <template x-for="f in filtered()" :key="f.family">
                <button type="button" @click="choose(f)"
                        class="flex w-full items-center justify-between gap-3 px-3 py-1.5 text-left text-sm hover:bg-paper"
                        :class="f.family === selected ? 'bg-accent-soft' : ''">
                    <span x-text="f.family" class="text-ink"></span>
                    <span class="text-[11px] text-faint" x-text="f.category"></span>
                </button>
            </template>
            <p x-show="filtered().length === 0" class="px-3 py-4 text-center text-xs text-faint">No fonts match.</p>
        </div>
    </div>
</div>
