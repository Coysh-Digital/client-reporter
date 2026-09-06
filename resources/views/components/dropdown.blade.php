@props([
    'align' => 'right',   // right | left
    'direction' => 'down', // down | up
    'width' => 'w-56',
])

{{-- A menu button. Put the trigger in the `trigger` slot; items are <x-dropdown-item>. --}}
<div x-data="crDropdown()" {{ $attributes->class(['relative inline-block']) }}
     x-on:keydown.escape.stop="close()"
     x-on:keydown.down.prevent="move(1)"
     x-on:keydown.up.prevent="move(-1)"
     x-on:keydown.tab="close(false)">
    <div x-ref="trigger" x-on:click="toggle()" x-bind:aria-expanded="open ? 'true' : 'false'" aria-haspopup="menu" class="inline-flex">
        {{ $trigger }}
    </div>
    <div x-show="open" x-cloak x-on:click.outside="close(false)" role="menu"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         @class([
             'cr-menu', $width,
             'right-0' => $align === 'right',
             'left-0' => $align === 'left',
             'top-full mt-1 origin-top' => $direction === 'down',
             'bottom-full mb-1 origin-bottom' => $direction === 'up',
         ])>
        {{ $slot }}
    </div>
</div>
