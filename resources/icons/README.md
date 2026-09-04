# UI icons

`ui.php` holds the icon path data used by the admin UI, rendered through the
`<x-icon name="...">` Blade component (`App\Support\Icons`). Icons are stored as
plain SVG path data so no icon-font runtime is shipped to the browser.

## Licensing

The icons are from [Heroicons](https://heroicons.com) v2 (solid style), which is
licensed under the [MIT License](https://github.com/tailwindlabs/heroicons/blob/master/LICENSE)
by Tailwind Labs.

The `grip-dots-vertical` drag handle is original to this project and shares
Client Reporter's MIT license.

## Adding an icon

Add an entry to `ui.php` keyed by the name you pass to `<x-icon>`, with the
icon's `width`/`height` (viewBox) and its `paths` (each `d` plus whether it needs
`fill-rule="evenodd"`). Copy the path data from a Heroicons solid SVG, or use any
other icon under a compatible license.
