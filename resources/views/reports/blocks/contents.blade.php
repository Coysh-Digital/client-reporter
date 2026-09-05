@php $rows = array_chunk($data['items'] ?? [], 3); @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: \App\Support\ReportLang::get('contents.heading'), 'icon' => $icon ?? 'document'])

@if (empty($data['items']))
    <p class="muted">{{ \App\Support\ReportLang::get('contents.empty') }}</p>
@else
    <table class="contents-grid">
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $item)
                    <td>
                        <a href="#{{ $item['anchor'] }}" class="contents-item">
                            <span class="contents-chip">@include('reports.blocks.partials.icon', ['key' => $item['icon'], 'color' => '#ffffff'])</span>
                            <span class="contents-label">{{ $item['heading'] }}</span>
                        </a>
                    </td>
                @endforeach
                @for ($i = count($row); $i < 3; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
@endif
