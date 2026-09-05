@props([
    'label' => null,
    'name' => '',
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'error' => null,
    'icon' => null,
    'helpText' => null,
    'value' => null,
])

@php
    if (is_array($value)) {
        $value = null;
    }
@endphp

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="{{ $icon }} text-gray-400"></i>
            </div>
        @endif

        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
            placeholder="{{ $placeholder }}" {{ $required ? 'required' : '' }} value="{{ $value }}"
            {{ $attributes->merge(['class' => 'w-full rounded-lg border-2 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-500/20 ' . ($icon ? 'pl-10 ' : '') . ($error ? 'border-red-500 dark:border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 dark:focus:border-blue-500') . ' px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500']) }}>
    </div>

    @if ($error)
        <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <i class="fas fa-exclamation-circle mr-1"></i>
            {{ $error }}
        </p>
    @endif

    @if ($helpText && !$error)
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $helpText }}
        </p>
    @endif
</div>
