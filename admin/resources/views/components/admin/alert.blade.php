@props([
    'type' => 'info', // success, error, warning, info
    'dismissible' => true,
    'icon' => null,
])

@php
    $types = [
        'success' => [
            'class' =>
                'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300',
            'icon' => 'fas fa-check-circle text-green-500',
        ],
        'error' => [
            'class' => 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300',
            'icon' => 'fas fa-exclamation-circle text-red-500',
        ],
        'warning' => [
            'class' =>
                'bg-yellow-50 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-300',
            'icon' => 'fas fa-exclamation-triangle text-yellow-500',
        ],
        'info' => [
            'class' =>
                'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300',
            'icon' => 'fas fa-info-circle text-blue-500',
        ],
    ];

    $typeConfig = $types[$type] ?? $types['info'];
    $displayIcon = $icon ?? $typeConfig['icon'];
@endphp

<div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-95"
    {{ $attributes->merge(['class' => "flex items-center p-4 mb-4 border-l-4 rounded-lg {$typeConfig['class']}"]) }}>
    <i class="{{ $displayIcon }} text-xl mr-3"></i>
    <div class="flex-1">
        {{ $slot }}
    </div>

    @if ($dismissible)
        <button @click="show = false" type="button"
            class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 hover:bg-black/10 dark:hover:bg-white/10 transition-colors">
            <i class="fas fa-times"></i>
        </button>
    @endif
</div>
