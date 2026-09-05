{{--
    Auto-generated "at a glance" summary shown at the top of a block, above the
    metric grid. $insight is a plain sentence built from the block's own
    resolved data (see App\Reporting\Support\Insight) — not staff commentary.
--}}
@if (! empty($insight))
    <div class="insight">
        <span class="insight-label">{{ \App\Support\ReportLang::get('common.summary_label') }}</span>
        {{ $insight }}
    </div>
@endif
