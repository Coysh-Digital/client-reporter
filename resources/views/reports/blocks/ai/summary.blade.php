@php $text = $data['ai_summary'] ?? null; @endphp
{{-- Report-level "month in review". Collapses to nothing when no summary was
     produced (AI off or generation failed) and there is no commentary, so a
     failed roundup never leaves an empty heading in the client's report. --}}
@if (! empty($text) || ! empty($commentary))
    @include('reports.blocks.partials.heading', ['text' => $heading ?: \App\Support\ReportLang::get('ai_summary.heading'), 'icon' => $icon ?? 'document'])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $text])
    @if ($commentary)
        <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
    @endif
@endif
