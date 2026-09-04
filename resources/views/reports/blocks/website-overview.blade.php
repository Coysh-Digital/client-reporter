@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Website overview', 'icon' => $icon ?? 'globe'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr>
                <th style="width: 30%;">Website</th>
                <td>{{ $data['host'] ?? '' }}</td>
            </tr>
            @if (! empty($data['cms']))
                <tr>
                    <th>Platform</th>
                    <td>{{ $data['cms'] }}</td>
                </tr>
            @endif
            <tr>
                <th>Environment</th>
                <td>{{ $data['environment'] ?? '' }}</td>
            </tr>
        </tbody>
    </table>
</div>

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
