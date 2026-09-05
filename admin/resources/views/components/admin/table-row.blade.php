@props([
    'clickable' => false,
    'href' => null,
])

@if ($href)
    <tr {{ $attributes->merge(['class' => 'hover:bg-blue-50 dark:hover:bg-gray-700/50 transition-colors duration-200 cursor-pointer']) }}
        onclick="window.location='{{ $href }}'">
        {{ $slot }}
    </tr>
@else
    <tr
        {{ $attributes->merge(['class' => $clickable ? 'hover:bg-blue-50 dark:hover:bg-gray-700/50 transition-colors duration-200 cursor-pointer' : '']) }}>
        {{ $slot }}
    </tr>
@endif
