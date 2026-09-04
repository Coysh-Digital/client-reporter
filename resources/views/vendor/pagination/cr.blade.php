{{--
    Themed pager for the app's light UI. Replaces Livewire's default Tailwind
    pagination (whose chevrons render dark against the warm light theme). Uses
    the app's CSS tokens so it stays legible in any theme, and drives Livewire's
    WithPagination methods (previousPage/nextPage/gotoPage) directly.
--}}
@if ($paginator->hasPages())
    @php $pageName = method_exists($paginator, 'getPageName') ? $paginator->getPageName() : 'page'; @endphp
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3">
        {{-- Range summary --}}
        <p class="hidden text-[12.5px] text-muted sm:block">
            @if ($paginator->firstItem())
                Showing <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
                of <span class="font-medium text-ink">{{ $paginator->total() }}</span>
            @else
                {{ $paginator->total() }} {{ Str::plural('result', $paginator->total()) }}
            @endif
        </p>

        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="cr-page cr-page-nav cr-page-disabled"><x-icon name="chevron-left" class="h-3.5 w-3.5" /></span>
            @else
                <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" rel="prev" aria-label="Previous" class="cr-page cr-page-nav"><x-icon name="chevron-left" class="h-3.5 w-3.5" /></button>
            @endif

            {{-- Numbered links (hidden on the smallest screens) --}}
            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="cr-page cr-page-gap">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="cr-page cr-page-current">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" class="cr-page">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Compact "page X of Y" on very small screens --}}
            <span class="cr-page cr-page-current sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" rel="next" aria-label="Next" class="cr-page cr-page-nav"><x-icon name="chevron-right" class="h-3.5 w-3.5" /></button>
            @else
                <span aria-disabled="true" class="cr-page cr-page-nav cr-page-disabled"><x-icon name="chevron-right" class="h-3.5 w-3.5" /></span>
            @endif
        </div>
    </nav>
@endif
