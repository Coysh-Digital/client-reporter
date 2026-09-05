<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a QR code as an inline SVG string — no external service, no imagick,
 * so it is safe to embed directly in a page (and works under a strict CSP).
 */
class QrCode
{
    public static function svg(string $data, int $size = 200): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($data);

        // Drop the XML prolog so the SVG can sit inline inside HTML.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
