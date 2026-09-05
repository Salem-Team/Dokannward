@props([
    'type' => 'button',
    'variant' => 'primary', // primary, secondary, success, danger, warning, gradient, outline
    'size' => 'md', // sm, md, lg
    'icon' => null,
    'iconPosition' => 'left', // left, right
    'loading' => false,
])

@php
    $variants = [
        'primary' => 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600 hover:border-blue-700',
        'secondary' => 'bg-gray-600 hover:bg-gray-700 text-white border-gray-600 hover:border-gray-700',
        'success' => 'bg-green-600 hover:bg-green-700 text-white border-green-600 hover:border-green-700',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white border-red-600 hover:border-red-700',
        'warning' => 'bg-yellow-500 hover:bg-yellow-600 text-white border-yellow-500 hover:border-yellow-600',
        'gradient' =>
            'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white border-0 shadow-lg shadow-purple-500/50',
        'outline' =>
            'bg-transparent hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-base',
        'lg' => 'px-6 py-3 text-lg',
    ];

    $variantClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<button type="{{ $type }}"
    {{ $attributes->merge(['class' => "admin-btn inline-flex items-center justify-center font-semibold rounded-lg border transition-all duration-300 transform hover:scale-105 active:scale-95 focus:outline-none focus:ring-4 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none {$variantClass} {$sizeClass}"]) }}
    @if ($loading) disabled @endif>
    @if ($loading)
        <svg class="animate-spin -ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </svg>
    @elseif($icon && $iconPosition === 'left')
        <i class="{{ $icon }} mr-2"></i>
    @endif

    {{ $slot }}

    @if ($icon && $iconPosition === 'right' && !$loading)
        <i class="{{ $icon }} ml-2"></i>
    @endif
</button>
