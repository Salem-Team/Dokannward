@extends('admin.layouts.app')

@section('title', 'Brands')

@section('content')
    @php
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
        $total = $brands->total();
        $onPage = $brands->count();
        $productsTotal = (int) $brands->sum('products_count');
        $emptyCount = $brands->where('products_count', 0)->count();
        $stockedCount = $onPage - $emptyCount;
        $countries = $brands->pluck('country')->filter()->unique()->count();
        $initialOpen = collect([$openBrandId ?? null])->filter()->values()->all();
    @endphp

    <div class="brand-studio-page brand-desk"
        x-data="{
            open: @js($initialOpen),
            query: '',
            stock: 'all',
            toggle(id) {
                if (this.open.includes(id)) {
                    this.open = this.open.filter((value) => value !== id);
                } else {
                    this.open = [...this.open, id];
                }
            },
            isOpen(id) {
                return this.open.includes(id);
            },
            matches(el) {
                const q = this.query.trim().toLowerCase();
                const name = (el.dataset.name || '').toLowerCase();
                const country = (el.dataset.country || '').toLowerCase();
                const slug = (el.dataset.slug || '').toLowerCase();
                const count = Number(el.dataset.count || 0);
                const textOk = !q || name.includes(q) || country.includes(q) || slug.includes(q);
                const stockOk =
                    this.stock === 'all'
                    || (this.stock === 'stocked' && count > 0)
                    || (this.stock === 'empty' && count === 0);
                return textOk && stockOk;
            },
            get visibleCount() {
                const rows = this.$refs.list
                    ? Array.from(this.$refs.list.querySelectorAll('.brand-desk__row'))
                    : [];
                return rows.filter((el) => this.matches(el)).length;
            },
        }">

        <header class="brand-desk__hero">
            <div class="brand-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">The house · Catalog</p>
                <h1 class="brand-studio-page__title">Brands</h1>
                <p class="brand-desk__lede">
                    Iconic houses that own products across the catalog.
                    Expand any brand to review its pieces without leaving this page.
                </p>
            </div>
            <div class="brand-desk__hero-actions">
                @if ($storefrontBase)
                    <a href="{{ $storefrontBase }}/brands" target="_blank" rel="noopener"
                        class="brand-desk__ghost-btn">
                        View live <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                    </a>
                @endif
                <a href="{{ route('admin.brands.create') }}" class="brand-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    New brand
                </a>
            </div>
        </header>
<section class="brand-desk__kpis" aria-label="Brand summary">
            <article class="brand-desk__kpi">
                <p class="brand-desk__kpi-label">Brands</p>
                <p class="brand-desk__kpi-value">{{ $total }}</p>
            </article>
            <article class="brand-desk__kpi">
                <p class="brand-desk__kpi-label">On this page</p>
                <p class="brand-desk__kpi-value">{{ $onPage }}</p>
            </article>
            <article class="brand-desk__kpi">
                <p class="brand-desk__kpi-label">With products</p>
                <p class="brand-desk__kpi-value">{{ $stockedCount }}</p>
            </article>
            <article class="brand-desk__kpi">
                <p class="brand-desk__kpi-label">Countries</p>
                <p class="brand-desk__kpi-value">{{ $countries }}</p>
            </article>
            <article class="brand-desk__kpi brand-desk__kpi--accent">
                <p class="brand-desk__kpi-label">Products nested</p>
                <p class="brand-desk__kpi-value">{{ $productsTotal }}</p>
                <p class="brand-desk__kpi-hint">{{ $emptyCount }} empty on page</p>
            </article>
        </section>

        <div class="brand-desk__tip" role="note">
            <span class="brand-desk__tip-mark" aria-hidden="true">Inspect</span>
            <div>
                <p class="brand-desk__tip-title">Brand products stay on this page</p>
                <p class="brand-desk__tip-text">
                    Use <strong>View products</strong> to open a house gallery here —
                    edit a piece, check stock, or add a new product under that brand.
                </p>
            </div>
        </div>

        <div class="brand-desk__toolbar">
            <label class="brand-desk__search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search"
                    x-model="query"
                    placeholder="Search brands, country, slug…"
                    autocomplete="off">
            </label>
            <div class="brand-desk__stock-filters" role="group" aria-label="Product fill">
                <button type="button" class="brand-desk__stock-btn"
                    :class="stock === 'all' && 'is-active'"
                    @click="stock = 'all'">All</button>
                <button type="button" class="brand-desk__stock-btn"
                    :class="stock === 'stocked' && 'is-active'"
                    @click="stock = 'stocked'">With products</button>
                <button type="button" class="brand-desk__stock-btn"
                    :class="stock === 'empty' && 'is-active'"
                    @click="stock = 'empty'">Empty</button>
            </div>
        </div>

        @if ($brands->isEmpty())
            <div class="brand-desk__empty">
                <p class="brand-desk__empty-eyebrow">The house</p>
                <h2 class="brand-desk__empty-title">No brands yet</h2>
                <p class="brand-desk__empty-text">
                    Add the first house — then assign products so the storefront brands index can fill.
                </p>
                <a href="{{ route('admin.brands.create') }}" class="brand-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Create first brand
                </a>
            </div>
        @else
            <div class="brand-desk__list" role="list" x-ref="list">
                @foreach ($brands as $index => $brand)
                    @php
                        $name = is_array($brand->name) ? ($brand->name['en'] ?? '') : $brand->name;
                        $nameAr = is_array($brand->name) ? ($brand->name['ar'] ?? null) : null;
                        $slug = $brand->slug;
                        $count = (int) $brand->products_count;
                        $products = $brand->products ?? collect();
                        $initial = mb_strtoupper(mb_substr((string) ($name ?: 'B'), 0, 1));
                        $desc = is_array($brand->description)
                            ? ($brand->description['en'] ?? '')
                            : ($brand->description ?? '');
                        $isAvatarLogo = $brand->logo_url && str_contains((string) $brand->logo_url, 'ui-avatars.com');
                    @endphp
                    <article
                        class="brand-desk__row"
                        style="--bd-i: {{ $index }};"
                        role="listitem"
                        data-name="{{ $name }} {{ $nameAr }}"
                        data-country="{{ $brand->country }}"
                        data-slug="{{ $slug }}"
                        data-count="{{ $count }}"
                        x-show="matches($el)"
                        x-transition.opacity.duration.150ms
                        :class="isOpen('{{ $brand->id }}') && 'is-open'">

                        <div class="brand-desk__row-main">
                            <div class="brand-desk__expand">
                                <button type="button"
                                    class="brand-desk__mark-btn"
                                    @click="toggle('{{ $brand->id }}')"
                                    :aria-expanded="isOpen('{{ $brand->id }}') ? 'true' : 'false'"
                                    aria-controls="brand-products-{{ $brand->id }}"
                                    title="View products for {{ $name }}">
                                    <span class="brand-desk__mark">
                                        @if ($brand->logo_url && ! $isAvatarLogo)
                                            <img src="{{ $brand->logo_url }}" alt="" loading="lazy">
                                        @else
                                            <span>{{ $initial }}</span>
                                        @endif
                                    </span>
                                </button>
                                <div class="brand-desk__identity">
                                    <div class="brand-desk__meta-line">
                                        <span class="brand-desk__kind">Brand</span>
                                        @if ($brand->country)
                                            <span class="brand-desk__dot" aria-hidden="true">·</span>
                                            <span>{{ $brand->country }}</span>
                                        @endif
                                        @if ($count > 0)
                                            <span class="brand-desk__dot" aria-hidden="true">·</span>
                                            <span class="brand-desk__pill">Active house</span>
                                        @endif
                                    </div>
                                    <button type="button"
                                        class="brand-desk__name-btn"
                                        @click="toggle('{{ $brand->id }}')"
                                        :aria-expanded="isOpen('{{ $brand->id }}') ? 'true' : 'false'"
                                        aria-controls="brand-products-{{ $brand->id }}">
                                        <span class="brand-desk__name">{{ $name }}</span>
                                    </button>
                                    @if ($nameAr && $nameAr !== $name)
                                        <p class="brand-desk__name-ar lang-ar" dir="rtl">{{ $nameAr }}</p>
                                    @endif
                                    @if ($desc)
                                        <p class="brand-desk__desc">{{ \Illuminate\Support\Str::limit(strip_tags($desc), 110) }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="brand-desk__side">
                                <div class="brand-desk__count-block">
                                    <p class="brand-desk__count-label">Products</p>
                                    <p class="brand-desk__count-value">{{ $count }}</p>
                                    @if ($slug)
                                        <code class="brand-desk__slug">/{{ $slug }}</code>
                                    @endif
                                </div>

                                <div class="brand-desk__actions">
                                    <button type="button"
                                        class="brand-desk__action is-accent"
                                        @click="toggle('{{ $brand->id }}')">
                                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                                        <span x-text="isOpen('{{ $brand->id }}') ? 'Hide products' : 'View products'"></span>
                                    </button>
                                    <a href="{{ route('admin.brands.edit', $brand->id) }}" class="brand-desk__action">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                        Edit
                                    </a>
                                    <a href="{{ route('admin.products.create', ['brand_id' => $brand->id]) }}"
                                        class="brand-desk__action">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                        Add
                                    </a>
                                    @if ($storefrontBase && $slug)
                                        <a href="{{ $storefrontBase }}/brands/{{ $slug }}"
                                            target="_blank" rel="noopener"
                                            class="brand-desk__action"
                                            title="View on storefront">
                                            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('admin.brands.destroy', $brand->id) }}" method="POST"
                                        data-confirm="Delete this brand? Products must be reassigned or removed first." data-confirm-title="Delete brand?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="brand-desk__action is-danger" title="Delete">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div id="brand-products-{{ $brand->id }}"
                            class="brand-desk__drawer"
                            x-show="isOpen('{{ $brand->id }}')"
                            x-collapse
                            x-cloak>

                            <div class="brand-desk__drawer-head">
                                <div>
                                    <p class="brand-desk__drawer-eyebrow">Brand gallery</p>
                                    <h3 class="brand-desk__drawer-title">
                                        {{ $name }}
                                        <em>{{ $count }} {{ $count === 1 ? 'piece' : 'pieces' }}</em>
                                    </h3>
                                </div>
                                <div class="brand-desk__drawer-links">
                                    <a href="{{ route('admin.products.index', ['brand' => $brand->id]) }}">
                                        Open in products desk
                                    </a>
                                    <a href="{{ route('admin.products.create', ['brand_id' => $brand->id]) }}">
                                        Add product
                                    </a>
                                </div>
                            </div>

                            @if ($products->isEmpty())
                                <div class="brand-desk__drawer-empty">
                                    <p>No products under this house yet.</p>
                                    <a href="{{ route('admin.products.create', ['brand_id' => $brand->id]) }}"
                                        class="brand-desk__primary-btn">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                        Add first product
                                    </a>
                                </div>
                            @else
                                <div class="brand-desk__products">
                                    @foreach ($products as $product)
                                        @php
                                            $photo = $product->photos->first();
                                            $productName = $product->translated_name;
                                            $categoryName = $product->category?->translated_name;
                                            $isActive = ($product->status ?? '') === 'active';
                                        @endphp
                                        <a href="{{ route('admin.products.edit', $product->id) }}"
                                            class="brand-desk__product">
                                            <span class="brand-desk__product-media">
                                                @if ($photo)
                                                    <img src="{{ $photo->url }}" alt="{{ $productName }}" loading="lazy">
                                                @else
                                                    <span class="brand-desk__product-fallback">
                                                        {{ mb_strtoupper(mb_substr((string) $productName, 0, 1)) }}
                                                    </span>
                                                @endif
                                                <span class="brand-desk__product-status {{ $isActive ? 'is-live' : 'is-off' }}">
                                                    {{ $isActive ? 'Active' : ucfirst((string) ($product->status ?: 'draft')) }}
                                                </span>
                                                @if ($product->featured)
                                                    <span class="brand-desk__product-featured">Featured</span>
                                                @endif
                                            </span>
                                            <span class="brand-desk__product-body">
                                                @if ($categoryName)
                                                    <span class="brand-desk__product-category">{{ $categoryName }}</span>
                                                @endif
                                                <span class="brand-desk__product-name">{{ $productName }}</span>
                                                <span class="brand-desk__product-meta">
                                                    <span>{{ \App\Support\Money::format($product->price) }}</span>
                                                    <span aria-hidden="true">·</span>
                                                    <span>{{ $product->in_stock ? 'In stock' : 'Out of stock' }}</span>
                                                </span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="brand-desk__no-match" x-show="visibleCount === 0" x-cloak>
                No brands match this search or filter.
            </p>

            @if ($brands->hasPages())
                <div class="brand-desk__pagination">
                    {{ $brands->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
