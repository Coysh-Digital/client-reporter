<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The icons shown on report section headers and the Contents section. dompdf
 * won't render an inline `<svg>` here, but it renders SVG through its image
 * pipeline, so each icon is a small monoline vector embedded as a data-URI
 * `<img>` (the same technique the report charts use). Clean and crisp on both
 * the web and the PDF, with the stroke colour substituted at render time.
 */
class ReportIcons
{
    /** @var array<int, string> */
    public const KEYS = ['chart', 'cart', 'search', 'pulse', 'wrench', 'receipt', 'globe', 'document', 'download'];

    /**
     * Inner SVG for each icon, on a 24×24 canvas. Every shape is stroked with
     * the render colour (no fills), so one colour drives the whole glyph.
     *
     * @var array<string, string>
     */
    private const ICONS = [
        'document' => '<path d="M13.5 3.5H6.75A1.25 1.25 0 0 0 5.5 4.75v14.5a1.25 1.25 0 0 0 1.25 1.25h10.5a1.25 1.25 0 0 0 1.25-1.25V8.5Z"/><path d="M13.5 3.5V8.5H18.5"/><path d="M8.75 12.75h6.5"/><path d="M8.75 16h4.5"/>',
        'chart' => '<path d="M4 20.25h16"/><path d="M7 20.25v-5.5"/><path d="M12 20.25V8.5"/><path d="M17 20.25v-8.5"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.6-4.6"/>',
        'pulse' => '<path d="M3.5 12.5h3.4l2-5.5 3.6 11 2.3-6.5 1.4 3.4h4.3"/>',
        'cart' => '<path d="M3 4.5h2l2.1 10.4a1 1 0 0 0 1 .8h7.8a1 1 0 0 0 1-.78L19.5 8H6.2"/><circle cx="9" cy="19" r="1.35"/><circle cx="17" cy="19" r="1.35"/>',
        'globe' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.6 12h16.8"/><path d="M12 3.6c3.1 2.4 3.1 14.4 0 16.8M12 3.6c-3.1 2.4-3.1 14.4 0 16.8"/>',
        'wrench' => '<path d="M20 12a8 8 0 0 1-13.66 5.66L4 15.5"/><path d="M4 20v-4.5h4.5"/><path d="M4 12A8 8 0 0 1 17.66 6.34L20 8.5"/><path d="M20 4v4.5h-4.5"/>',
        'receipt' => '<path d="M6.5 3.5h11v17l-1.8-1.3-1.8 1.3-1.9-1.3-1.8 1.3-1.9-1.3-1.8 1.3z"/><path d="M9.5 8.5h5"/><path d="M9.5 12h5"/>',
        'download' => '<path d="M12 4v10.5"/><path d="M8 11l4 4 4-4"/><path d="M5 20h14"/>',
    ];

    /**
     * A coloured icon as a data-URI `<img>`, sized to fill its container. Falls
     * back to the document icon for an unknown key.
     */
    public static function html(string $key, string $color = '#8a6a2c'): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
            .'stroke="'.$color.'" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
            .(self::ICONS[$key] ?? self::ICONS['document'])
            .'</svg>';

        return '<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" alt="" style="display:block;width:100%;height:auto;">';
    }
}
