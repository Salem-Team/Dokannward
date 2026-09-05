@extends('admin.layouts.app')

@section('title', 'Product Details')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <x-admin.button variant="outline" icon="fas fa-arrow-left"
                    onclick="window.location='{{ route('admin.products.index') }}'">
                    Back
                </x-admin.button>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $product->getTranslatedNameAttribute() }}
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">SKU: {{ $product->sku }}</p>
                </div>
            </div>

            <div class="flex gap-2">
                <x-admin.button variant="primary" icon="fas fa-edit"
                    onclick="window.location='{{ route('admin.products.edit', $product->id) }}'">
                    Edit Product
                </x-admin.button>

                <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST"
                    data-confirm="Delete this product permanently? Variants and gallery images are removed. Past order lines stay in history."
                    data-confirm-title="Delete product?"
                    data-confirm-confirm="Delete product"
                    data-confirm-tone="danger"
                    data-confirm-eyebrow="Destructive action">
                    @csrf
                    @method('DELETE')
                    <x-admin.button type="submit" variant="danger" icon="fas fa-trash">
                        Delete
                    </x-admin.button>
                </form>
            </div>
        </div>

        <!-- Status Badges -->
        <div class="flex gap-3">
            @if ($product->status === 'active')
                <x-admin.badge variant="success" icon="fas fa-check" size="lg">Active</x-admin.badge>
            @else
                <x-admin.badge variant="danger" icon="fas fa-times" size="lg">Inactive</x-admin.badge>
            @endif

            @if ($product->featured)
                <x-admin.badge variant="primary" icon="fas fa-star" size="lg">Featured</x-admin.badge>
            @endif

            @php
                $stock = $product->variants->sum('stock') ?: 0;
                $stockColor = $stock > 10 ? 'success' : ($stock > 0 ? 'warning' : 'danger');
            @endphp
            <x-admin.badge :variant="$stockColor" icon="fas fa-boxes" size="lg">
                Stock: {{ $stock }} units
            </x-admin.badge>
        </div>

        <x-admin.card title="Dokan Ward Product Code" icon="fas fa-qrcode" variant="default">
            @include('admin.products._product-qr', ['product' => $product])
        </x-admin.card>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Images -->
            <div class="lg:col-span-1">
                <x-admin.card title="Product Images" icon="fas fa-images" variant="default">
                    @if ($product->photos->count() > 0)
                        @php
                            $cover = $product->coverPhoto() ?? $product->photos->first();
                        @endphp
                        <div class="space-y-4">
                            <!-- Main Image -->
                            <img id="main-image" src="{{ $cover->url }}"
                                alt="{{ $product->getTranslatedNameAttribute() }}"
                                class="w-full h-64 object-cover rounded-lg shadow-lg">

                            <!-- Thumbnail Grid -->
                            @if ($product->photos->count() > 1)
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach ($product->photos as $photo)
                                        <img src="{{ $photo->url }}"
                                            alt="{{ $photo->alt_text }}"
                                            class="w-full h-16 object-cover rounded-lg cursor-pointer hover:opacity-75 transition-opacity border-2 {{ $cover->id === $photo->id ? 'border-blue-500' : 'border-transparent' }} hover:border-blue-500"
                                            onclick="document.getElementById('main-image').src = this.src">
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <i class="fas fa-image text-6xl mb-4"></i>
                            <p>No images available</p>
                        </div>
                    @endif
                </x-admin.card>
            </div>

            <!-- Product Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info -->
                <x-admin.card title="Product Information" icon="fas fa-info-circle" variant="default">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Description</h3>
                            <p class="mt-2 text-gray-900 dark:text-white">
                                {{ $product->getTranslatedDescriptionAttribute() ?? 'No description available' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Category</h3>
                                <p class="mt-1 text-gray-900 dark:text-white">
                                    {{ $product->category ? $product->category->getTranslatedNameAttribute() : 'N/A' }}</p>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Collections</h3>
                                <p class="mt-1 text-gray-900 dark:text-white">
                                    {{ $product->collections->isNotEmpty() ? $product->collections->pluck('translated_name')->implode(', ') : 'Not merchandised in a collection' }}
                                </p>
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Brand</h3>
                                <p class="mt-1 text-gray-900 dark:text-white">
                                    {{ $product->brand ? $product->brand->getTranslatedNameAttribute() : 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </x-admin.card>

                <!-- Pricing -->
                @php
                    $showBase = (float) ($product->price_max ?? $product->price ?? 0);
                    $showSale = (float) ($product->price ?? 0);
                    $showOnSale = $showBase > $showSale && $showSale >= 0;
                    $showSavePct = $showOnSale ? (int) round((($showBase - $showSale) / $showBase) * 100) : 0;
                @endphp
                <x-admin.card title="Pricing" icon="fas fa-dollar-sign" variant="gradient" padding="p-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="box-fit">
                            <h3 class="text-sm font-semibold text-white/80 uppercase">
                                {{ $showOnSale ? 'Original price' : 'Price' }}
                            </h3>
                            <p class="fit-num fit-num--lg mt-2 font-bold text-white {{ $showOnSale ? 'line-through opacity-70' : '' }}">
                                LE {{ number_format($showBase, 2) }}
                            </p>
                        </div>

                        @if ($showOnSale)
                            <div class="box-fit">
                                <h3 class="text-sm font-semibold text-white/80 uppercase">Sale price</h3>
                                <p class="fit-num fit-num--lg mt-2 font-bold text-white">
                                    LE {{ number_format($showSale, 2) }}
                                </p>
                                <p class="mt-1 text-sm text-white/60">
                                    Save {{ $showSavePct }}%
                                </p>
                            </div>
                        @endif
                    </div>
                </x-admin.card>

                <!-- Specifications -->
                <x-admin.card title="Specifications" icon="fas fa-list" variant="default">
                    <div class="grid grid-cols-2 gap-4">
                        @if ($product->material)
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fas fa-scroll text-blue-500 text-xl"></i>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Material</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $product->material }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->color)
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fas fa-palette text-purple-500 text-xl"></i>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Color</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $product->color }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->dimensions)
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fas fa-ruler-combined text-green-500 text-xl"></i>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Dimensions</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $product->dimensions }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($product->weight)
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <i class="fas fa-weight text-yellow-500 text-xl"></i>
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Weight</p>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $product->weight }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-admin.card>
            </div>
        </div>

        <!-- Product Variants -->
        @if ($product->variants->count() > 0)
            <x-admin.card title="Product Variants" icon="fas fa-layer-group" variant="default">
                <x-admin.table :headers="['SKU', 'Color', 'Size', 'Price', 'Stock', 'Actions']" hoverable>
                    @foreach ($product->variants as $variant)
                        <x-admin.table-row>
                            <x-admin.table-cell>
                                <span class="font-mono text-sm">{{ $variant->sku }}</span>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                {{ $variant->color->name ?? 'N/A' }}
                                @if ($variant->color_id && $variant->is_default)
                                    <span class="ml-2 inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                        <i class="fas fa-star"></i> Primary
                                    </span>
                                @endif
                            </x-admin.table-cell>
                            <x-admin.table-cell>{{ $variant->size->name ?? 'N/A' }}</x-admin.table-cell>
                            <x-admin.table-cell>
                                <span class="font-bold text-green-600 dark:text-green-400">
                                    ${{ number_format($variant->price ?? 0, 2) }}
                                </span>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                @php
                                    $variantStock = $variant->stock;
                                    $stockColor =
                                        $variantStock > 10 ? 'success' : ($variantStock > 0 ? 'warning' : 'danger');
                                @endphp
                                <x-admin.badge :variant="$stockColor">{{ $variantStock }} units</x-admin.badge>
                            </x-admin.table-cell>
                            <x-admin.table-cell>
                                <button
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </x-admin.table-cell>
                        </x-admin.table-row>
                    @endforeach
                </x-admin.table>
            </x-admin.card>
        @endif

        <!-- Reviews -->
        @if ($product->reviews->count() > 0)
            <x-admin.card title="Customer Reviews" icon="fas fa-star" variant="default">
                <x-slot name="actions">
                    <a href="{{ route('admin.reviews.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        Manage all reviews <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </x-slot>
                <div class="space-y-4">
                    @foreach ($product->reviews->take(5) as $review)
                        <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <span
                                        class="font-semibold text-gray-900 dark:text-white">{{ $review->author_name }}</span>
                                    <div class="flex">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i
                                                class="fas fa-star text-sm {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}"></i>
                                        @endfor
                                    </div>
                                    @if ($review->approved)
                                        <x-admin.badge variant="success" size="sm">Approved</x-admin.badge>
                                    @else
                                        <x-admin.badge variant="warning" size="sm">Pending</x-admin.badge>
                                    @endif
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $review->created_at->diffForHumans() }}
                                </span>
                            </div>
                            @if ($review->title)
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $review->title }}</p>
                            @endif
                            <p class="text-gray-700 dark:text-gray-300">{{ $review->body }}</p>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        @endif
    </div>
@endsection
