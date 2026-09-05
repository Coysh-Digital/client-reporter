{{--
    Shared block heading: a brand-coloured icon chip + section title, with an
    optional right-aligned "Source: X" badge (which provider produced this
    block's data). $variant 'title' is the larger free-form-block style
    (text/closing); default is the section-header style used by every data block.
    Icons render in the brand primary's contrast colour inside the chip.
--}}
@if (($variant ?? 'eyebrow') === 'title')
    <h2 class="block-title">
        <span class="block-heading-chip">@include('reports.blocks.partials.icon', ['key' => $icon ?? 'document', 'color' => '#ffffff'])</span>
        <span class="block-title-body">{{ $text }}</span>
    </h2>
@else
    <table class="block-heading-row"><tr>
        <td class="block-heading">
            <span class="block-heading-chip">@include('reports.blocks.partials.icon', ['key' => $icon ?? 'document', 'color' => '#ffffff'])</span>
            <span class="block-heading-title">{{ $text }}</span>
        </td>
        @if (! empty($suffix ?? null))
            <td class="block-heading-source">
                <span class="block-heading-source-label">Source</span>
                <span class="block-heading-source-badge">{{ $suffix }}</span>
            </td>
        @endif
    </tr></table>
@endif
