@extends('admin.layouts.app')

@section('title', 'Reviews')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header title="Product Reviews"
            subtitle="Approve, reject or delete customer reviews before they appear on the storefront.">
            <x-slot name="actions">
                @if ($counts['pending'] > 0)
                    <form action="{{ route('admin.reviews.approveAll') }}" method="POST"
                        data-confirm="Approve all {{ $counts['pending'] }} pending review(s)?" data-confirm-title="Approve reviews?" data-confirm-confirm="Approve all" data-confirm-tone="warning" data-confirm-eyebrow="Moderation">
                        @csrf
                        <x-admin.button variant="success" icon="fas fa-check-double" type="submit">
                            Approve All Pending ({{ $counts['pending'] }})
                        </x-admin.button>
                    </form>
                @endif
            </x-slot>
        </x-admin.page-header>

        <div class="admin-filter-tabs">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.reviews.index', ['status' => $key]) }}"
                    class="admin-filter-tabs__link {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }}
                    <span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>
                </a>
            @endforeach
        </div>

        <!-- Reviews Table -->
        <x-admin.card>
            <x-admin.table hoverable>
                <x-slot name="header">
                    <x-admin.table-row>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Product
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Reviewer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Rating
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Review
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </x-admin.table-row>
                </x-slot>

                @forelse ($reviews as $review)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            @if ($review->product)
                                <a href="{{ route('admin.products.show', $review->product->id) }}"
                                    class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $review->product->translated_name }}
                                </a>
                            @else
                                <span class="text-gray-400 italic">Deleted product</span>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $review->author_name }}</p>
                            @if ($review->reviewer_email)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $review->reviewer_email }}</p>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <div class="flex items-center">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="fas fa-star text-sm {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}"></i>
                                @endfor
                            </div>
                        </x-admin.table-cell>
                        <x-admin.table-cell class="max-w-sm">
                            @if ($review->title)
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $review->title }}</p>
                            @endif
                            @if ($review->body)
                                <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2">{{ $review->body }}</p>
                            @endif
                            @if (!$review->title && !$review->body)
                                <span class="text-sm text-gray-400 italic">No written comment</span>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            @if ($review->approved)
                                <x-admin.badge variant="success" size="sm">Approved</x-admin.badge>
                            @else
                                <x-admin.badge variant="warning" size="sm">Pending</x-admin.badge>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $review->created_at->format('M d, Y') }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell class="text-right">
                            <div class="flex items-center justify-end gap-3">
                                @if (!$review->approved)
                                    <form action="{{ route('admin.reviews.approve', $review->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" title="Approve"
                                            class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.reviews.reject', $review->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" title="Unapprove"
                                            class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.reviews.destroy', $review->id) }}" method="POST" class="inline"
                                    data-confirm="Delete this review permanently?" data-confirm-title="Delete review?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="7" class="text-center py-8">
                            <i class="fas fa-star text-4xl text-gray-300 dark:text-gray-600 mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400">
                                @if ($status === 'pending')
                                    No pending reviews — you're all caught up.
                                @else
                                    No reviews found.
                                @endif
                            </p>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table>

            @if ($reviews->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $reviews->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
