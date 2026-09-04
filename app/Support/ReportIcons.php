<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fixed icons shown in a report's Contents section. Built from plain
 * HTML/CSS (borders, border-radius, background — no SVG) because dompdf does
 * not render inline `<svg>` at all in this install (confirmed: even a single
 * bare `<rect>` renders nothing), which is also why the analytics chart uses
 * table-based bars rather than an SVG sparkline. Each icon is calibrated to a
 * fixed 20×20 canvas — the size every caller uses — with only the colour
 * substituted at render time; there is no per-agency override.
 */
class ReportIcons
{
    /** @var array<int, string> */
    public const KEYS = ['chart', 'cart', 'search', 'pulse', 'wrench', 'receipt', 'globe', 'document'];

    /** 20×20 HTML/CSS icons; `{c}` is substituted with the render colour. */
    private const DEFAULTS = [
        'chart' => '<div style="position:relative;width:20px;height:20px;"><table style="border-collapse:collapse;border:0;position:absolute;left:1px;bottom:1px;"><tr>
            <td style="border:0;vertical-align:bottom;padding:0 1px;"><div style="width:4px;height:7px;background:{c};">&nbsp;</div></td>
            <td style="border:0;vertical-align:bottom;padding:0 1px;"><div style="width:4px;height:12px;background:{c};">&nbsp;</div></td>
            <td style="border:0;vertical-align:bottom;padding:0 1px;"><div style="width:4px;height:17px;background:{c};">&nbsp;</div></td>
        </tr></table></div>',
        'cart' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:4px;top:9px;width:12px;height:9px;background:{c};border-radius:0 0 2px 2px;"></div>
            <div style="position:absolute;left:7px;top:3px;width:7px;height:7px;border:2px solid {c};border-bottom:none;border-radius:50% 50% 0 0;"></div>
        </div>',
        'search' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:1px;top:1px;width:11px;height:11px;border:2px solid {c};border-radius:50%;"></div>
            <div style="position:absolute;left:11px;top:11px;width:8px;height:2px;background:{c};transform:rotate(45deg);"></div>
        </div>',
        'pulse' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:2px;top:6px;width:16px;height:8px;border:2px solid {c};border-bottom:none;border-radius:16px 16px 0 0;"></div>
            <div style="position:absolute;left:9px;top:6px;width:2px;height:8px;background:{c};transform:rotate(25deg);transform-origin:bottom;"></div>
        </div>',
        'wrench' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:3px;top:3px;width:14px;height:14px;border:2px solid {c};border-top-color:transparent;border-radius:50%;transform:rotate(45deg);"></div>
        </div>',
        'receipt' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:4px;top:2px;width:12px;height:16px;border:1.5px solid {c};border-radius:1px;"></div>
            <div style="position:absolute;left:6px;top:7px;width:8px;height:1.5px;background:{c};"></div>
            <div style="position:absolute;left:6px;top:11px;width:8px;height:1.5px;background:{c};"></div>
        </div>',
        'globe' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:2px;top:2px;width:16px;height:16px;border:1.5px solid {c};border-radius:50%;"></div>
            <div style="position:absolute;left:6px;top:2px;width:8px;height:16px;border:1.5px solid {c};border-radius:50%;"></div>
            <div style="position:absolute;left:2px;top:9.5px;width:16px;height:1.5px;background:{c};"></div>
        </div>',
        'document' => '<div style="position:relative;width:20px;height:20px;">
            <div style="position:absolute;left:3px;top:5px;width:14px;height:1.5px;background:{c};"></div>
            <div style="position:absolute;left:3px;top:9px;width:14px;height:1.5px;background:{c};"></div>
            <div style="position:absolute;left:3px;top:13px;width:9px;height:1.5px;background:{c};"></div>
        </div>',
    ];

    /**
     * The HTML for an icon key, coloured. Falls back to the 'document' icon
     * for an unrecognised key.
     */
    public static function html(string $key, string $color = '#8a6a2c'): string
    {
        $template = self::DEFAULTS[$key] ?? self::DEFAULTS['document'];

        return str_replace('{c}', $color, $template);
    }
}
