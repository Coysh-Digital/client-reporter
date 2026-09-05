{{--
    Shared metric row for summary blocks. Expects:
      $metrics  array of ['label','fmt','goodUp','current','previous'(nullable)]
      $currency (optional) for 'money' formatting
    A null 'previous' hides the delta (per-block comparison off, or no data).
--}}
@php use App\Support\Format; use App\Support\ReportLang; @endphp
<table class="metric-grid">
    <tr>
        @foreach ($metrics as $m)
            @php
                $current = $m['current'] ?? null;
                $previous = $m['previous'] ?? null;
                $change = Format::change($current, $previous);
                $isGood = in_array($change['direction'], ['flat', 'none'], true)
                    ? null
                    : (($change['direction'] === 'up') === ($m['goodUp'] ?? true));
                $arrow = ['up' => '+', 'down' => '-', 'flat' => '', 'none' => ''][$change['direction']];
                $deltaClass = $isGood === null ? 'delta-flat' : ($isGood ? 'delta-up' : 'delta-down');
                $value = Format::forType($current, $m['fmt'] ?? 'number', $currency ?? null);
            @endphp
            <td>
                <div class="metric-label">{{ $m['label'] }}</div>
                <div class="metric-value">{{ $value }}</div>
                @if ($previous !== null && $change['percent'] !== null)
                    <div class="delta {{ $deltaClass }}">{{ ReportLang::get('common.vs_prev', ['delta' => $arrow.Format::number(abs($change['percent']), 1)]) }}</div>
                @elseif ($previous !== null)
                    <div class="delta delta-flat">{{ ReportLang::get('common.no_change') }}</div>
                @endif
            </td>
        @endforeach
    </tr>
</table>
