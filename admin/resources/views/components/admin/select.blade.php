@props([
    'label' => null,
    'name' => '',
    'required' => false,
    'error' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'helpText' => null,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <select id="{{ $name }}" name="{{ $name }}" {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-2 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-500/20 ' . ($error ? 'border-red-500 dark:border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 dark:focus:border-blue-500') . ' px-4 py-3 text-gray-900 dark:text-white']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" {{ $selected == $value ? 'selected' : '' }}>
                {{ is_array($optionLabel) ? $optionLabel[app()->getLocale()] ?? ($optionLabel['en'] ?? reset($optionLabel)) : $optionLabel }}
            </option>
        @endforeach
    </select>

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
