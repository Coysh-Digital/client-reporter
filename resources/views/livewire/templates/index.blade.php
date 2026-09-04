<div>
    <x-page-header title="Report templates" subtitle="Reusable section layouts you can apply to any new report." eyebrow="Portfolio">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" wire:navigate class="cr-btn cr-btn-secondary">
                <x-icon name="file-chart-column" class="h-3.5 w-3.5" />
                Reports
            </a>
            <a href="{{ route('templates.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                <x-icon name="plus" class="h-3.5 w-3.5" />
                New template
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($templates->isEmpty())
        <x-empty-state icon="layer-group" title="No templates yet"
                       description="Create a template to reuse a set of sections — cover, analytics, uptime and more — across your clients’ reports.">
            <x-slot:action>
                <a href="{{ route('templates.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                <x-icon name="plus" class="h-3.5 w-3.5" />
                New template
            </a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="cr-panel">
            @foreach ($templates as $template)
                <div wire:key="tpl-{{ $template->id }}" class="flex items-center gap-4 px-5 py-4 hover:bg-paper"
                     @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                    <a href="{{ route('templates.edit', $template) }}" wire:navigate class="min-w-0 flex-1">
                        <span class="block truncate text-[14.5px] font-semibold text-ink">{{ $template->name }}</span>
                        @if ($template->description)
                            <span class="block truncate text-[12.5px] text-faint">{{ $template->description }}</span>
                        @endif
                    </a>
                    <span class="tnum text-[13px] text-muted">{{ count($template->blocks) }} {{ Str::plural('section', count($template->blocks)) }}</span>
                    <div class="flex items-center gap-3 text-[12.5px]">
                        <a href="{{ route('templates.edit', $template) }}" wire:navigate class="font-semibold" style="color:var(--color-accent)">Edit</a>
                        <button wire:click="delete({{ $template->id }})" wire:confirm="Delete the “{{ $template->name }}” template?"
                                class="text-faint hover:text-danger" title="Delete">
                            <x-icon name="trash-can" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
