@props([
    'align' => 'left', // left, center, right
])

@php
    $alignments = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ];

    $alignClass = $alignments[$align] ?? $alignments['left'];
@endphp

<td {{ $attributes->merge(['class' => "px-6 py-4 font-medium text-gray-900 dark:text-white {$alignClass}"]) }}>
    {{ $slot }}
</td>
