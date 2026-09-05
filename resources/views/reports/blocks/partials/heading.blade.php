{{--
    Shared block heading: a brand-coloured icon chip + section title, with an
    optional right-aligned "Source: X" badge (which provider produced this
    block's data). $variant 'title' is the larger free-form-block style
    (text/closing). Laid out as a table (no floats) so the chip and title never
    collide across a PDF page break. Icons render in the chip's contrast colour.
--}}
@php $isTitle = ($variant ?? 'eyebrow') === 'title'; @endphp
<table class="block-heading-row"><tr>
    <td class="block-heading-chip-cell">
        <span class="block-heading-chip">@include('reports.blocks.partials.icon', ['key' => $icon ?? 'document', 'color' => '#ffffff'])</span>
    </td>
    <td class="{{ $isTitle ? 'block-title-cell' : 'block-heading-title-cell' }}">{{ $text }}</td>
    @if (! $isTitle && ! empty($suffix ?? null))
        <td class="block-heading-source">
            <span class="block-heading-source-label">Source</span>
            <span class="block-heading-source-badge">{{ $suffix }}</span>
        </td>
    @endif
</tr></table>
