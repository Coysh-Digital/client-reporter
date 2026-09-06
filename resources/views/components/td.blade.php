@props(['align' => 'left', 'nowrap' => false])

<td {{ $attributes->class(['text-right' => $align === 'right', 'text-center' => $align === 'center', 'whitespace-nowrap' => $nowrap]) }}>
    {{ $slot }}
</td>
