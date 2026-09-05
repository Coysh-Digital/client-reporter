{{--
    An optional AI-written summary shown at the top of a block, below the
    deterministic "Summary". $aiSummary is plain text produced at generate time
    (see App\Ai\AiSummariser). Table/inline-block + plain CSS only, so it renders
    identically on the web and through dompdf.
--}}
@if (! empty($aiSummary))
    <div class="ai-summary">
        <span class="ai-summary-label">{{ $branding->aiSummaryLabel ?? \App\Support\ReportLang::get('common.ai_summary_label') }}</span>
        {{ $aiSummary }}
    </div>
@endif
