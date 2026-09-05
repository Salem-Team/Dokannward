@props([
    'label' => null,
    'name' => '',
    'checked' => false,
    'helpText' => null,
])

<div class="flex items-center justify-between mb-4">
    <div class="flex-1">
        @if ($label)
            <label for="{{ $name }}" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ $label }}
            </label>
        @endif
        @if ($helpText)
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $helpText }}
            </p>
        @endif
    </div>

    <label for="{{ $name }}" class="relative inline-flex items-center cursor-pointer ml-4">
        <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" {{ $checked ? 'checked' : '' }}
            class="sr-only peer" {{ $attributes }}>
        <div
            class="w-14 h-7 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-gradient-to-r peer-checked:from-blue-500 peer-checked:to-purple-500 shadow-lg">
        </div>
    </label>
</div>
