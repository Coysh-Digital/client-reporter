{{--
    Renders one Contents-section icon. $key is a ReportIcons key. Fixed,
    built-in HTML/CSS only — never SVG, since dompdf does not render inline
    <svg> at all here.
--}}
{!! \App\Support\ReportIcons::html($key ?? 'document', $color ?? ($branding->secondaryColor ?? '#8a6a2c')) !!}
