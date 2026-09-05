@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default', // default, gradient, glass, hover
    'padding' => 'p-4 sm:p-5 lg:p-6',
])

@php
    $variants = [
        'default' => 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700',
        'gradient' => 'bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 text-white border-0',
        'glass' => 'bg-white/80 dark:bg-gray-800/80 backdrop-blur-lg border border-gray-200/50 dark:border-gray-700/50',
        'hover' =>
            'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:shadow-2xl hover:scale-105 transition-all duration-300',
    ];

    $variantClass = $variants[$variant] ?? $variants['default'];
@endphp

<div {{ $attributes->merge(['class' => "admin-x-card rounded-xl shadow-lg {$variantClass} {$padding}"]) }}>
    @isset($header)
        <div class="mb-4">
            {{ $header }}
        </div>
    @elseif ($title || $icon)
        <div class="admin-x-card__head">
            <div class="admin-x-card__head-main">
                @if ($icon)
                    <div class="admin-x-card__icon">
                        <i class="{{ $icon }}" aria-hidden="true"></i>
                    </div>
                @endif
                <div class="min-w-0">
                    @if ($title)
                        <h3 class="admin-x-card__title {{ $variant === 'gradient' ? 'text-white' : 'text-gray-900 dark:text-white' }}">
                            {{ $title }}
                        </h3>
                    @endif
                    @if ($subtitle)
                        <p class="admin-x-card__subtitle {{ $variant === 'gradient' ? 'text-white/80' : '' }}">
                            {{ $subtitle }}
                        </p>
                    @endif
                </div>
            </div>
            @if (isset($actions))
                <div class="admin-x-card__actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $variant === 'gradient' ? 'text-white' : 'text-gray-700 dark:text-gray-300' }}">
        {{ $slot }}
    </div>
</div>
