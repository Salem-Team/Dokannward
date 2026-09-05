@props([
    'variant' => 'default', // default, primary, success, danger, warning, info
    'size' => 'md', // sm, md, lg
    'icon' => null,
    'dot' => false,
])

@php
    $variants = [
        'default' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'primary' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        'info' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',
    ];

    $sizes = [
        'sm' => 'text-xs px-2 py-0.5',
        'md' => 'text-sm px-2.5 py-1',
        'lg' => 'text-base px-3 py-1.5',
    ];

    $variantClass = $variants[$variant] ?? $variants['default'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full {$variantClass} {$sizeClass}"]) }}>
    @if ($dot)
        <span class="w-2 h-2 rounded-full mr-1.5 bg-current animate-pulse"></span>
    @endif

    @if ($icon)
        <i class="{{ $icon }} mr-1"></i>
    @endif

    {{ $slot }}
</span>
