@extends('admin.layouts.app')

@section('title', 'Edit Collection')

@section('content')
    <div class="brand-studio-page space-y-8">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Catalog</p>
                <h1 class="brand-studio-page__title">Edit collection</h1>
                <p class="brand-studio-page__sub">Update identity and cover, then manage the products merchandised in this group.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @php
                    $liveSlug = is_array($collection->slug) ? ($collection->slug['en'] ?? '') : $collection->slug;
                    $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
                @endphp
                @if ($storefrontBase && $liveSlug)
                    <x-admin.button variant="secondary" icon="fas fa-external-link-alt" size="sm"
                        onclick="window.open('{{ $storefrontBase }}/collections/{{ $liveSlug }}', '_blank')">
                        View live
                    </x-admin.button>
                @endif
                <x-admin.button variant="primary" icon="fas fa-box" size="sm"
                    onclick="window.location='{{ route('admin.products.create', ['collection_id' => $collection->id]) }}'">
                    Add product
                </x-admin.button>
                <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                    onclick="window.location='{{ route('admin.collections.index') }}'">
                    Back
                </x-admin.button>
            </div>
        </div>

        @include('admin.collections._roadmap', [
            'variant' => 'full',
            'collection' => $collection,
        ])

        @include('admin.collections._form', [
            'action' => route('admin.collections.update', $collection->id),
            'collection' => $collection,
        ])

        <section class="brand-studio__section">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div>
                    <p class="brand-studio__eyebrow">Merchandising</p>
                    <h2 class="brand-studio__section-title">Products in this collection</h2>
                    <p class="brand-studio__hint mt-1">
                        Direct members from any category. Edit a product to change which collections it belongs to.
                    </p>
                </div>
                <x-admin.button variant="secondary" icon="fas fa-plus" size="sm"
                    onclick="window.location='{{ route('admin.products.create', ['collection_id' => $collection->id]) }}'">
                    Add product
                </x-admin.button>
            </div>

            <x-admin.card>
                <x-admin.table>
                    <x-slot name="header">
                        <x-admin.table-row>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-[0.14em]">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-[0.14em]">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-[0.14em]">Brand</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-[0.14em]">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-[0.14em]">Actions</th>
                        </x-admin.table-row>
                    </x-slot>

                    @forelse ($collection->products as $product)
                        @php
                            $productName = is_array($product->name) ? ($product->name['en'] ?? '') : $product->name;
                            $thumb = $product->photos->first()?->url ?? null;
                        @endphp
                        <x-admin.table-row>
                            <x-admin.table-cell>
                                <div class="flex items-center gap-3 min-w-0">
                                    @if ($thumb)
                                        <img src="{{ $thumb }}" alt="" class="w-10 h-10 object-cover border border-zibra-line shrink-0">
                                    @else
                                        <div class="w-10 h-10 shrink-0 border border-zibra-line bg-zibra-paper flex items-center justify-center text-xs font-semibold text-zibra-ash">
                                            {{ mb_strtoupper(mb_substr((string) $productName, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <span class="font-medium text-gray-900 dark:text-white truncate block">{{ $productName }}</span>
                                        <code class="text-xs font-mono text-gray-500">{{ $product->sku }}</code>
                                    </div>
                                </div>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $product->category?->translated_name ?? '—' }}
                                </span>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $product->brand?->translated_name ?? '—' }}
                                </span>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <x-admin.badge :variant="$product->status === 'active' ? 'success' : 'secondary'">
                                    {{ $product->status === 'active' ? 'Active' : 'Draft' }}
                                </x-admin.badge>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <a href="{{ route('admin.products.edit', $product->id) }}"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 px-2.5 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                                    title="Edit product">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @empty
                        <x-admin.table-row>
                            <x-admin.table-cell colspan="5" class="text-center py-8 text-gray-500">
                                No products in this collection yet.
                                <a href="{{ route('admin.products.create', ['collection_id' => $collection->id]) }}" class="underline font-medium text-primary">
                                    Add one
                                </a>.
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @endforelse
                </x-admin.table>
            </x-admin.card>
        </section>
    </div>
@endsection

@push('scripts')
    @include('admin.collections._form-scripts')
@endpush
