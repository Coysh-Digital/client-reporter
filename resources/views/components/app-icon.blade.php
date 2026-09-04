{{--
    Client Reporter's own product mark — a geometric "C" ring, used consistently
    as the in-app brand mark and (rasterized once into public/favicon.*) the
    browser favicon. Inline SVG so it's always crisp with no extra request.
--}}
<svg {{ $attributes->merge(['class' => 'h-8 w-8', 'viewBox' => '0 0 64 64', 'xmlns' => 'http://www.w3.org/2000/svg']) }}>
    <rect width="64" height="64" rx="14" fill="#33406b"/>
    <path d="M49.202,44.045 A21,21 0 1 1 49.202,19.955 L41.011,25.691 A11,11 0 1 0 41.011,38.309 Z" fill="#ffffff"/>
</svg>
