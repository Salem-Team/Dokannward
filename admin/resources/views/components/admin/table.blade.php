@props([
    'headers' => [],
    'hoverable' => true,
    'striped' => false,
])

<div class="admin-table-shell">
    <table {{ $attributes->merge(['class' => 'admin-table text-left']) }}>
        <thead
            class="text-xs uppercase bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 text-gray-700 dark:text-gray-300 border-b-2 border-gray-200 dark:border-gray-700">
            @isset($header)
                {{ $header }}
            @else
                <tr>
                    @foreach ($headers as $headerLabel)
                        <th scope="col" class="px-6 py-4 font-bold tracking-wider">
                            {{ $headerLabel }}
                        </th>
                    @endforeach
                </tr>
            @endisset
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            {{ $slot }}
        </tbody>
    </table>
</div>

<style>
    @if ($hoverable)
        tbody tr {
            @apply hover:bg-blue-50 dark:hover:bg-gray-700/50 transition-colors duration-200 cursor-pointer;
        }
    @endif

    @if ($striped)
        tbody tr:nth-child(even) {
            @apply bg-gray-50 dark:bg-gray-900/50;
        }
    @endif
</style>
