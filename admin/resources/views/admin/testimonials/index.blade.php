@extends('admin.layouts.app')

@section('title', 'Testimonials')

@section('content')
    <div class="brand-studio-page space-y-6">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Homepage</p>
                <h1 class="brand-studio-page__title">Testimonials</h1>
                <p class="brand-studio-page__sub">
                    Customer stories shown under “What our clients say”. Keep Active on for live display.
                </p>
            </div>
            <a href="{{ route('admin.testimonials.create') }}">
                <x-admin.button variant="primary" icon="fas fa-plus">
                    Add Testimonial
                </x-admin.button>
            </a>
        </div>

        <div class="website-hub__tip" role="note">
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">How they appear</p>
                <p class="website-hub__tip-text">
                    Up to 3 active stories show on the homepage. Featured ones come first, then Display Order.
                    Choose the social platform so the brand logo and color appear on the card.
                </p>
            </div>
        </div>

        <x-admin.card>
            <x-admin.table hoverable>
                <x-slot name="header">
                    <x-admin.table-row>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Customer
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Content
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Rating
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Source
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Order
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </x-admin.table-row>
                </x-slot>

                @forelse ($testimonials as $testimonial)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <div class="flex items-center gap-3">
                                @if ($testimonial->avatar)
                                    <img src="{{ $testimonial->avatar }}" alt=""
                                        class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-600">
                                @else
                                    <span
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-[11px] font-bold tracking-wide text-gray-800 dark:text-gray-100">
                                        {{ collect(preg_split('/\s+/', trim($testimonial->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}
                                    </span>
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $testimonial->name }}</p>
                                    @if ($testimonial->title || $testimonial->company)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ collect([$testimonial->title, $testimonial->company])->filter()->implode(' · ') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 max-w-md">
                                {{ $testimonial->content }}
                            </p>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <div class="flex items-center gap-0.5" aria-label="{{ $testimonial->rating }} of 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="fas fa-star text-sm {{ $i <= $testimonial->rating ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}"></i>
                                @endfor
                            </div>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            @php $sourceMeta = \App\Support\TestimonialSources::get($testimonial->source); @endphp
                            @if ($sourceMeta)
                                <span class="inline-flex items-center gap-2 text-xs font-semibold"
                                    style="color: {{ $sourceMeta['color'] }}">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-white text-[11px]"
                                        style="background: {{ $sourceMeta['color'] }}">
                                        <i class="{{ $sourceMeta['icon'] }}"></i>
                                    </span>
                                    {{ $sourceMeta['label'] }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <div class="flex flex-col gap-1">
                                @if ($testimonial->is_active)
                                    <x-admin.badge variant="success" size="sm">Active</x-admin.badge>
                                @else
                                    <x-admin.badge variant="danger" size="sm">Inactive</x-admin.badge>
                                @endif
                                @if ($testimonial->is_featured)
                                    <x-admin.badge variant="primary" size="sm">Featured</x-admin.badge>
                                @endif
                            </div>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $testimonial->display_order }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.testimonials.edit', $testimonial) }}"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.testimonials.destroy', $testimonial) }}" method="POST"
                                    class="inline" data-turbo="false"
                                    data-confirm="Delete “{{ $testimonial->name }}”? This cannot be undone."
                                    data-confirm-title="Delete testimonial?"
                                    data-confirm-confirm="Delete"
                                    data-confirm-tone="danger"
                                    data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="7" class="text-center py-12">
                            <i class="fas fa-comment-dots text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">No testimonials yet</p>
                            <a href="{{ route('admin.testimonials.create') }}">
                                <x-admin.button variant="primary" icon="fas fa-plus" type="button">
                                    Add your first story
                                </x-admin.button>
                            </a>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table>

            @if ($testimonials->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $testimonials->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
