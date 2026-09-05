{{--
    Self-contained, fully white-labelled report document. Deliberately uses
    table/inline-block layout and plain CSS (no flexbox/grid) so the identical
    markup renders on the web and through the dompdf PDF driver on shared hosting.
    $branding is a ResolvedBranding; $blocks is an array of
    ['view','type','heading','commentary','data'].
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title }} · {{ $branding->agencyName }}</title>
    {{-- White-labelled: only ever the agency's own favicon, never Client Reporter's. --}}
    @if ($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    {{-- Load the agency's chosen web fonts; ignored by dompdf, which falls back to the stack. --}}
    @php
        $fontUrl = \App\Support\GoogleFonts::googleUrl([
            \App\Support\GoogleFonts::extractFamily($branding->headingFont),
            \App\Support\GoogleFonts::extractFamily($branding->bodyFont),
        ]);
    @endphp
    @if ($fontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fontUrl }}" rel="stylesheet">
    @endif
    <style>
        :root {
            --brand-primary: {{ $branding->primaryColor }};
            --brand-secondary: {{ $branding->secondaryColor }};
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #ece7dd;
            color: #211f1b;
            font-family: {!! $branding->bodyFont !!};
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .report { max-width: 800px; margin: 0 auto; padding: 32px 16px 64px; }
        .sheet {
            background: #fffdf9;
            border: 1px solid #e2dccf;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 20px 60px -24px rgba(40, 34, 20, .28), 0 2px 8px rgba(40, 34, 20, .05);
        }
        h1, h2, h3 { font-family: {!! $branding->headingFont !!}; font-weight: 600; letter-spacing: -0.01em; margin: 0; }
        .block { padding: 34px 46px; border-top: 1px solid #ede6d8; }
        .block:first-child { border-top: 0; }
        /* Gently marks the section the builder preview just scrolled to. */
        .block:target { box-shadow: inset 3px 0 0 var(--brand-primary); }
        /* Section header: a brand icon chip + title, laid out as a table (no
           floats) so it survives PDF page breaks. Shared by every block. */
        .block-heading-row { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .block-heading-chip-cell { width: 30px; padding: 0; vertical-align: middle; }
        .block-heading-chip { display: block; width: 30px; height: 30px; border-radius: 8px; background: var(--brand-primary); padding: 5px; }
        .block-heading-title-cell { padding: 0 0 0 12px; vertical-align: middle; font-family: {!! $branding->headingFont !!}; font-size: 16px; font-weight: 600; letter-spacing: -0.01em; color: #201e1a; }
        .block-title-cell { padding: 0 0 0 12px; vertical-align: middle; font-family: {!! $branding->headingFont !!}; font-size: 22px; font-weight: 600; letter-spacing: -0.01em; color: #201e1a; }
        .block-heading-source { width: 1%; white-space: nowrap; text-align: right; vertical-align: middle; padding-left: 14px; }
        .block-heading-source-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #98938a; }
        .block-heading-source-badge { display: inline-block; margin-left: 6px; padding: 3px 11px; border-radius: 999px; background: #f3ecdf; font-size: 11px; font-weight: 600; color: #4a463d; }
        .table-scroll { overflow-x: auto; }
        /* Callouts: an editorial accent bar in a brand colour. */
        .insight { background: #faf7ef; border: 1px solid #efe7d3; border-left: 3px solid var(--brand-secondary); border-radius: 7px; padding: 12px 16px 12px 18px; margin: 0 0 20px; font-size: 13.5px; line-height: 1.6; color: #4a4638; }
        .insight-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--brand-secondary); margin-bottom: 4px; }
        .ai-summary { background: #faf7ef; border: 1px solid #ece5da; border-left: 3px solid var(--brand-primary); border-radius: 7px; padding: 12px 16px 12px 18px; margin: 0 0 20px; font-size: 13.5px; line-height: 1.6; color: #4a4638; }
        .ai-summary-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--brand-primary); margin-bottom: 4px; }
        /* Headline metric tile, shared by the consolidated blocks. A min-height
           keeps a row of tiles level even when one label wraps. */
        .metric-tile { border: 1px solid #ece5d6; border-top: 2px solid var(--brand-primary); border-radius: 9px; padding: 13px 15px; background: #fffdfa; min-height: 84px; }
        .metric-tile .metric-label { min-height: 15px; }
        .contents-grid { width: 100%; border-collapse: collapse; margin-top: 4px; table-layout: fixed; }
        .contents-grid td { width: 33.33%; padding: 0 10px 12px 0; vertical-align: top; }
        .contents-item { display: block; padding: 13px 14px; border: 1px solid #ece5d6; border-radius: 9px; overflow: hidden; }
        .contents-chip { display: block; float: left; width: 26px; height: 26px; border-radius: 7px; background: var(--brand-primary); padding: 5px; }
        .contents-label { display: block; margin-left: 36px; padding-top: 4px; font-size: 13.5px; font-weight: 600; color: #211f1b; }
        .commentary { margin-top: 16px; color: #57534a; font-size: 14.5px; line-height: 1.65; }
        .muted { color: #8b857a; }
        .metric-grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .metric-grid td { width: 25%; padding: 4px 14px 4px 0; vertical-align: top; }
        .metric-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #8b857a; }
        .metric-value { font-family: {!! $branding->headingFont !!}; font-size: 27px; color: #211f1b; margin-top: 3px; font-variant-numeric: tabular-nums; }
        .delta { font-size: 12.5px; margin-top: 2px; font-variant-numeric: tabular-nums; }
        .delta-up { color: #3f7d54; }
        .delta-down { color: #a13b32; }
        .delta-flat, .delta-none { color: #98938a; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13.5px; font-variant-numeric: tabular-nums; }
        table.data th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: var(--brand-secondary); border-bottom: 1px solid #efe8da; padding: 7px 8px; }
        table.data td { padding: 8px; border-bottom: 1px solid #f3ecdf; color: #3c3931; }
        table.data tr:last-child td { border-bottom: 0; }
        /* dompdf-safe horizontal bar list (table-based). */
        table.bars { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.bars td { padding: 5px 0; vertical-align: middle; font-size: 13px; }
        table.bars .bar-track { display: block; height: 6px; background: #eee7d9; border-radius: 3px; overflow: hidden; }
        table.bars .bar-fill { display: block; height: 6px; background: var(--brand-primary); border-radius: 3px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; }
        .mini-bars-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: var(--brand-secondary); }
        .status-strip { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 12px; }
        .status-strip td { padding: 0 1px; }
        .status-cell { display: block; height: 20px; border-radius: 3px; font-size: 0; line-height: 0; }
        .gauge-ring { display: inline-block; width: 66px; height: 66px; border-radius: 50%; border: 5px solid #eee7d9; text-align: center; }
        .gauge-value { font-family: {!! $branding->headingFont !!}; font-size: 22px; line-height: 56px; }
        .report-footer { text-align: center; color: #9a9384; font-size: 12.5px; margin-top: 22px; line-height: 1.7; }
        .report-footer .footer-name { font-family: {!! $branding->headingFont !!}; font-size: 15px; font-weight: 600; color: #4a463d; display: block; margin-bottom: 4px; }
        a { color: var(--brand-primary); text-decoration: none; }

        @media (max-width: 640px) {
            body { overflow-x: hidden; }
            .report { padding: 20px 10px 48px; }
            .block { padding: 24px 20px !important; }
            .cover-band { margin: -24px -20px 0 !important; padding: 36px 20px 30px !important; }
            .cover-band h1 { font-size: 32px !important; }
            .cover-minimal h1 { font-size: 30px !important; }
            .contents-grid, .contents-grid tbody, .contents-grid tr, .contents-grid td {
                display: block; width: 100% !important;
            }
            .contents-grid td { padding: 0 0 10px; }
            .metric-grid, .metric-grid tbody, .metric-grid tr, .metric-grid td {
                display: block; width: 100% !important;
            }
            .metric-grid td { padding: 10px 0; border-bottom: 1px solid #f3ecdf; }
            .metric-grid tr td:last-child { border-bottom: 0; }
            table.data { font-size: 12.5px; }
            /* Stack fixed-width tile rows (headline tiles, mini-bar columns) one
               per row so they never cram at ~360px. width:100%!important beats the
               inline width:20%/25% these tables carry for the desktop/PDF layout. */
            .tile-grid, .tile-grid tbody, .tile-grid tr, .tile-grid td {
                display: block; width: 100% !important;
            }
            .tile-grid td { padding: 10px 0 !important; }
            /* Lighthouse-style gauge rows go two-up rather than fully stacked. */
            .gauge-grid, .gauge-grid tbody, .gauge-grid tr { display: block; }
            .gauge-grid td { display: inline-block; width: 48% !important; padding: 10px 0 !important; vertical-align: top; }
            /* Two-column cover rows stack, dropping the desktop right-alignment. */
            .cover-split, .cover-split tbody, .cover-split tr, .cover-split td {
                display: block; width: 100% !important;
            }
            .cover-split td { text-align: left !important; white-space: normal !important; padding-left: 0 !important; padding-right: 0 !important; }
            /* Long query strings / URLs wrap instead of forcing horizontal scroll. */
            table.data td { word-break: break-word; }
        }
        @if ($branding->customCss) {!! $branding->customCss !!} @endif
    </style>
</head>
<body>
    <div class="report">
        <div class="sheet">
            @foreach ($blocks as $b)
                <section id="block-{{ $b['id'] ?? $loop->index }}" class="block block--{{ $b['type'] }}">
                    @includeIf($b['view'], ['data' => $b['data'], 'heading' => $b['heading'], 'commentary' => $b['commentary'], 'icon' => $b['icon'] ?? 'document', 'branding' => $branding, 'report' => $report])
                </section>
            @endforeach
        </div>

        <div class="report-footer">
            @if ($branding->reportFooter)
                <span class="footer-name">{{ $branding->agencyName }}</span>{{ $branding->reportFooter }}
            @else
                <span class="footer-name">{{ $branding->agencyName }}</span>
            @endif
        </div>
    </div>
</body>
</html>
