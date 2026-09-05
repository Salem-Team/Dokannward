@extends('admin.layouts.app')

@section('title', 'Inventory')

@section('content')
    @php
        $search = request('search');
        $brand = request('brand');
        $category = request('category');
        $reserved = request('reserved');
        $availability = request('availability');
        $sort = request('sort', 'stock_asc');
        $activeStatus = $stockStatus ?: 'all';
        $from = $variants->firstItem() ?? 0;
        $to = $variants->lastItem() ?? 0;
        $filterParams = array_filter([
            'search' => $search,
            'brand' => $brand,
            'category' => $category,
            'reserved' => $reserved,
            'availability' => $availability,
            'sort' => $sort !== 'stock_asc' ? $sort : null,
        ]);
    @endphp

    <div class="brand-studio-page inventory-desk">
        <header class="inventory-desk__hero">
            <div class="inventory-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Operations · Stock</p>
                <h1 class="brand-studio-page__title">Inventory</h1>
                <p class="inventory-desk__lede">
                    Real-time stock across every SKU — filter by maison, category, or availability,
                    then adjust quantities in one click.
                </p>
            </div>
            <div class="inventory-desk__hero-actions">
                <a href="{{ route('admin.products.index') }}" class="inventory-desk__ghost-btn">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    Products
                </a>
                <a href="{{ route('admin.settings.index') }}" class="inventory-desk__primary-btn">
                    <i class="fas fa-sliders-h" aria-hidden="true"></i>
                    Stock settings
                </a>
            </div>
        </header>

        <section class="inventory-desk__kpis" aria-label="Inventory summary">
            <article class="inventory-desk__kpi">
                <p class="inventory-desk__kpi-label">Total SKUs</p>
                <p class="inventory-desk__kpi-value">{{ number_format($stats['total']) }}</p>
            </article>
            <article class="inventory-desk__kpi inventory-desk__kpi--alert">
                <p class="inventory-desk__kpi-label">Out of stock</p>
                <p class="inventory-desk__kpi-value">{{ number_format($stats['out_of_stock']) }}</p>
            </article>
            <article class="inventory-desk__kpi">
                <p class="inventory-desk__kpi-label">Low stock</p>
                <p class="inventory-desk__kpi-value">{{ number_format($stats['low_stock']) }}</p>
                <p class="inventory-desk__kpi-hint">≤ 10 units</p>
            </article>
            <article class="inventory-desk__kpi">
                <p class="inventory-desk__kpi-label">Units on hand</p>
                <p class="inventory-desk__kpi-value">{{ number_format($stats['total_units']) }}</p>
            </article>
            <article class="inventory-desk__kpi inventory-desk__kpi--accent">
                <p class="inventory-desk__kpi-label">Reserved</p>
                <p class="inventory-desk__kpi-value">{{ number_format($stats['reserved_units']) }}</p>
                <p class="inventory-desk__kpi-hint">Held for open orders</p>
            </article>
        </section>

        <div class="inventory-desk__pipeline" role="tablist" aria-label="Stock status">
            @foreach ($statusChips as $key => $label)
                @php
                    $chipParams = $key === 'all'
                        ? $filterParams
                        : array_merge($filterParams, ['stock_status' => $key]);
                    $href = route('admin.inventory.index', $chipParams);
                    $active = $activeStatus === $key;
                    $count = $pipeline[$key] ?? 0;
                @endphp
                <a href="{{ $href }}"
                    class="inventory-desk__chip{{ $active ? ' is-active' : '' }}{{ $key === 'out' ? ' is-danger' : '' }}{{ $key === 'low' ? ' is-warning' : '' }}"
                    role="tab"
                    aria-selected="{{ $active ? 'true' : 'false' }}">
                    {{ $label }} <em>{{ number_format($count) }}</em>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.inventory.index') }}" class="inventory-desk__filters">
            @if ($activeStatus !== 'all')
                <input type="hidden" name="stock_status" value="{{ $activeStatus }}">
            @endif

            <label class="inventory-desk__search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ $search }}"
                    placeholder="Search SKU or product name…" autocomplete="off">
            </label>

            <select name="brand" class="inventory-desk__select" aria-label="Filter by brand">
                <option value="">All maisons</option>
                @foreach ($brands as $id => $name)
                    <option value="{{ $id }}" @selected($brand === $id)>{{ $name }}</option>
                @endforeach
            </select>

            <select name="category" class="inventory-desk__select" aria-label="Filter by category">
                <option value="">All categories</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}" @selected($category === $id)>{{ $name }}</option>
                @endforeach
            </select>

            <select name="reserved" class="inventory-desk__select" aria-label="Filter by reservations">
                <option value="">All reservations</option>
                <option value="yes" @selected($reserved === 'yes')>Has reserved</option>
                <option value="no" @selected($reserved === 'no')>No reservations</option>
            </select>

            <select name="availability" class="inventory-desk__select" aria-label="Filter by availability">
                <option value="">All availability</option>
                <option value="available" @selected($availability === 'available')>Available to sell</option>
                <option value="unavailable" @selected($availability === 'unavailable')>Unavailable</option>
            </select>

            <select name="sort" class="inventory-desk__select" aria-label="Sort results">
                <option value="stock_asc" @selected($sort === 'stock_asc')>Stock · low first</option>
                <option value="stock_desc" @selected($sort === 'stock_desc')>Stock · high first</option>
                <option value="available_asc" @selected($sort === 'available_asc')>Available · low first</option>
                <option value="available_desc" @selected($sort === 'available_desc')>Available · high first</option>
                <option value="sku_asc" @selected($sort === 'sku_asc')>SKU · A–Z</option>
                <option value="sku_desc" @selected($sort === 'sku_desc')>SKU · Z–A</option>
            </select>

            <button type="submit" class="inventory-desk__primary-btn inventory-desk__filter-submit">
                <i class="fas fa-filter" aria-hidden="true"></i>
                Apply
            </button>
            <a href="{{ route('admin.inventory.index') }}" class="inventory-desk__ghost-btn">Reset</a>
        </form>

        <div class="inventory-desk__tip" role="note">
            <span class="inventory-desk__tip-mark" aria-hidden="true">Stock health</span>
            <div>
                <p class="inventory-desk__tip-title">Read the levels at a glance</p>
                <p class="inventory-desk__tip-text">
                    <strong>Out</strong> = zero on hand ·
                    <strong>Low</strong> = 1–10 ·
                    <strong>Medium</strong> = 11–30 ·
                    <strong>Healthy</strong> = 31+.
                    Reserved units are held for open orders and reduce sellable availability.
                </p>
            </div>
        </div>

        @if ($variants->isEmpty())
            <div class="inventory-desk__empty">
                <p class="inventory-desk__empty-eyebrow">Stock desk</p>
                <h2 class="inventory-desk__empty-title">No SKUs match these filters</h2>
                <p class="inventory-desk__empty-text">
                    Try widening your search or clearing filters to see the full catalog.
                </p>
                <a href="{{ route('admin.inventory.index') }}" class="inventory-desk__primary-btn">
                    Clear filters
                </a>
            </div>
        @else
            <div class="inventory-desk__results-bar">
                <p class="inventory-desk__results-count">
                    Showing
                    <strong>{{ $from }}–{{ $to }}</strong>
                    of
                    <strong>{{ number_format($variants->total()) }}</strong>
                    SKUs
                </p>
            </div>

            <div class="inventory-desk__table-wrap">
                <table class="inventory-desk__table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Maison</th>
                            <th scope="col">Variant</th>
                            <th scope="col" class="is-numeric">Stock</th>
                            <th scope="col" class="is-numeric">Reserved</th>
                            <th scope="col" class="is-numeric">Available</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($variants as $index => $variant)
                            @php
                                $product = $variant->product;
                                $productName = $product
                                    ? (is_array($product->name) ? ($product->name['en'] ?? '') : $product->name)
                                    : '—';
                                $brandName = $product?->brand?->translated_name;
                                $categoryName = $product?->category?->translated_name;
                                $cover = $product?->coverPhoto();
                                $imageUrl = $cover?->url;
                                $initials = collect(explode(' ', $productName))
                                    ->filter()
                                    ->map(fn ($w) => mb_substr($w, 0, 1))
                                    ->take(2)
                                    ->join('');
                                $reservedQty = (int) ($variant->stock_reserved ?? 0);
                                $available = max(0, $variant->stock - $reservedQty);
                                $stockLevel = match (true) {
                                    $variant->stock === 0 => 'out',
                                    $variant->stock <= 10 => 'low',
                                    $variant->stock <= 30 => 'medium',
                                    default => 'healthy',
                                };
                                $variantParts = array_filter([
                                    $variant->color?->name,
                                    $variant->size?->name,
                                ]);
                            @endphp
                            <tr class="inventory-desk__row inventory-desk__row--{{ $stockLevel }}"
                                style="--inv-i: {{ $index }};">
                                <td class="inventory-desk__product">
                                    <div class="inventory-desk__product-cell">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="" class="inventory-desk__thumb" loading="lazy">
                                        @else
                                            <div class="inventory-desk__thumb inventory-desk__thumb--placeholder" aria-hidden="true">
                                                {{ $initials ?: '—' }}
                                            </div>
                                        @endif
                                        <div class="inventory-desk__product-meta">
                                            <p class="inventory-desk__product-name">{{ $productName }}</p>
                                            @if ($categoryName)
                                                <p class="inventory-desk__product-category">{{ $categoryName }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code class="inventory-desk__sku">{{ $variant->sku }}</code>
                                </td>
                                <td>
                                    <span class="inventory-desk__brand">{{ $brandName ?: '—' }}</span>
                                </td>
                                <td>
                                    @if (count($variantParts))
                                        <span class="inventory-desk__variant">{{ implode(' · ', $variantParts) }}</span>
                                    @else
                                        <span class="inventory-desk__variant is-muted">Default</span>
                                    @endif
                                </td>
                                <td class="is-numeric">
                                    <span class="inventory-desk__stock-pill is-{{ $stockLevel }}">
                                        {{ number_format($variant->stock) }}
                                    </span>
                                </td>
                                <td class="is-numeric">
                                    <span class="inventory-desk__reserved{{ $reservedQty > 0 ? ' has-value' : '' }}">
                                        {{ number_format($reservedQty) }}
                                    </span>
                                </td>
                                <td class="is-numeric">
                                    <span class="inventory-desk__available{{ $available <= 0 ? ' is-zero' : '' }}">
                                        {{ number_format($available) }}
                                    </span>
                                </td>
                                <td class="inventory-desk__actions">
                                    <button type="button"
                                        class="inventory-desk__edit-btn"
                                        onclick="openStockModal('{{ $variant->id }}', {{ $variant->stock }})"
                                        title="Update stock for {{ $variant->sku }}">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                        <span>Adjust</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($variants->hasPages())
                <div class="inventory-desk__pagination">
                    {{ $variants->links() }}
                </div>
            @endif
        @endif
    </div>

    <x-admin.modal id="stockModal" title="Update stock">
        <form id="stockForm" method="POST" action="">
            @csrf
            <x-admin.input type="number" name="stock" id="stockInput" label="Stock quantity" required min="0" />
            <p class="inventory-desk__modal-hint">
                Changes apply immediately. Low-stock alerts trigger at 10 units or below.
            </p>
            <div class="flex justify-end gap-3 mt-6">
                <x-admin.button variant="secondary" type="button" onclick="window.modal.close('stockModal')">
                    Cancel
                </x-admin.button>
                <x-admin.button variant="primary" type="submit">
                    Save stock
                </x-admin.button>
            </div>
        </form>
    </x-admin.modal>

    <script>
        function openStockModal(variantId, currentStock) {
            document.getElementById('stockInput').value = currentStock;
            document.getElementById('stockForm').action = `/admin/inventory/${variantId}/stock`;
            window.modal.open('stockModal');
        }
    </script>
@endsection
