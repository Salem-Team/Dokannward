@props([
    'title' => '',
    'value' => '',
    'icon' => 'fas fa-chart-line',
    'trend' => null, // positive, negative, neutral
    'trendValue' => null,
    'color' => 'blue', // blue, green, red, yellow, purple, pink
])

@php
    $colors = [
        'blue' => 'from-blue-500 to-blue-600',
        'green' => 'from-green-500 to-green-600',
        'red' => 'from-red-500 to-red-600',
        'yellow' => 'from-yellow-500 to-yellow-600',
        'purple' => 'from-purple-500 to-purple-600',
        'pink' => 'from-pink-500 to-pink-600',
    ];

    $gradientClass = $colors[$color] ?? $colors['blue'];
@endphp

<div
    class="box-fit bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
    <div class="p-6">
        <div class="flex items-center justify-between gap-3 mb-4 min-w-0">
            <div class="flex-1 min-w-0">
                <p class="fit-text text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                    {{ $title }}
                </p>
                <p class="fit-num fit-num--lg font-bold text-gray-900 dark:text-white mt-2">
                    {{ $value }}
                </p>
            </div>
            <div class="shrink-0 p-4 bg-gradient-to-br {{ $gradientClass }} rounded-xl shadow-lg">
                <i class="{{ $icon }} text-2xl text-white"></i>
            </div>
        </div>

        @if ($trend && $trendValue)
            <div class="flex items-center mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                @if ($trend === 'positive')
                    <i class="fas fa-arrow-up text-green-500 mr-2"></i>
                    <span class="text-sm font-semibold text-green-600 dark:text-green-400">{{ $trendValue }}</span>
                @elseif($trend === 'negative')
                    <i class="fas fa-arrow-down text-red-500 mr-2"></i>
                    <span class="text-sm font-semibold text-red-600 dark:text-red-400">{{ $trendValue }}</span>
                @else
                    <i class="fas fa-minus text-gray-500 mr-2"></i>
                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">{{ $trendValue }}</span>
                @endif
                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">vs last month</span>
            </div>
        @endif
    </div>

    <!-- Optional action slot -->
    @if (isset($action))
        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            {{ $action }}
        </div>
    @endif
</div>
