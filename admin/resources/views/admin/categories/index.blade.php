@extends('admin.layouts.app')

@section('title', 'Categories')

@section('content')
    @php
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
        $total = $categories->count();
        $productsTotal = (int) $categories->sum('products_count');
        $emptyCount = $categories->where('products_count', 0)->count();
        $stockedCount = $total - $emptyCount;
        $featuredCount = $categories->where('is_featured', true)->count();
        $logoCount = $categories->filter(fn ($c) => filled($c->logo_url))->count();
        $initialOpen = collect([$openCategoryId])->filter()->values()->all();
    @endphp

    <div class="brand-studio-page category-desk"
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
                const slug = (el.dataset.slug || '').toLowerCase();
                const count = Number(el.dataset.count || 0);
                const featured = el.dataset.featured === '1';
                const hasLogo = el.dataset.logo === '1';
                const textOk = !q || name.includes(q) || slug.includes(q);
                const stockOk =
                    this.stock === 'all'
                    || (this.stock === 'stocked' && count > 0)
                    || (this.stock === 'empty' && count === 0)
                    || (this.stock === 'homepage' && featured)
                    || (this.stock === 'logo' && hasLogo);
                return textOk && stockOk;
            },
            get visibleCount() {
                const rows = this.$refs.list
                    ? Array.from(this.$refs.list.querySelectorAll('.category-desk__card'))
                    : [];
                return rows.filter((el) => this.matches(el)).length;
            },
        }">

        <header class="category-desk__hero">
            <div class="category-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Catalog · Classification</p>
                <h1 class="brand-studio-page__title">Categories</h1>
                <p class="category-desk__lede">
                    Classification only — what each item is. Banner, logo mark, story, and product fill.
                    Merchandising groups live under Collections.
                </p>
            </div>
            <div class="category-desk__hero-actions">
                <a href="{{ route('admin.collections.index') }}" class="category-desk__ghost-btn">
                    Collections
                </a>
                <a href="{{ route('admin.categories.create') }}" class="category-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    New category
                </a>
            </div>
        </header>

        <section class="category-desk__kpis" aria-label="Category summary">
            <article class="category-desk__kpi">
                <p class="category-desk__kpi-label">Categories</p>
                <p class="category-desk__kpi-value">{{ $total }}</p>
            </article>
            <article class="category-desk__kpi">
                <p class="category-desk__kpi-label">With products</p>
                <p class="category-desk__kpi-value">{{ $stockedCount }}</p>
            </article>
            <article class="category-desk__kpi">
                <p class="category-desk__kpi-label">On homepage</p>
                <p class="category-desk__kpi-value">{{ $featuredCount }}</p>
            </article>
            <article class="category-desk__kpi">
                <p class="category-desk__kpi-label">With logo</p>
                <p class="category-desk__kpi-value">{{ $logoCount }}</p>
            </article>
            <article class="category-desk__kpi category-desk__kpi--accent">
                <p class="category-desk__kpi-label">Products classified</p>
                <p class="category-desk__kpi-value">{{ $productsTotal }}</p>
                <p class="category-desk__kpi-hint">{{ $emptyCount }} empty</p>
            </article>
        </section>

        <div class="category-desk__tip" role="note">
            <span class="category-desk__tip-mark" aria-hidden="true">The Edit</span>
            <div>
                <p class="category-desk__tip-title">Homepage banner + logo rail</p>
                <p class="category-desk__tip-text">
                    <strong>Logo</strong> appears under the hero for every category that has one
                    (matches “With logo”).
                    <strong>Banner + Featured</strong> power the Shop by category stack
                    (matches “On homepage”) — then use <strong>Position</strong> to order them.
                </p>
            </div>
        </div>

        @if ($categories->isEmpty())
            <div class="category-desk__empty">
                <div class="category-desk__empty-frame" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <p class="category-desk__empty-eyebrow">Start the structure</p>
                <h2 class="category-desk__empty-title">No categories yet</h2>
                <p class="category-desk__empty-text">
                    Create a category with a cover and story, then assign products so customers can browse.
                </p>
                <div class="category-desk__empty-actions">
                    <a href="{{ route('admin.collections.index') }}" class="category-desk__ghost-btn">Collections</a>
                    <a href="{{ route('admin.categories.create') }}" class="category-desk__primary-btn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Add category
                    </a>
                </div>
            </div>
        @else
            <div class="category-desk__toolbar">
                <div class="category-desk__controls">
                    <label class="category-desk__search">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search"
                            x-model="query"
                            placeholder="Search categories…"
                            autocomplete="off">
                    </label>
                    <div class="category-desk__stock-filters" role="group" aria-label="Product fill">
                        <button type="button" class="category-desk__stock-btn"
                            :class="stock === 'all' && 'is-active'"
                            @click="stock = 'all'">All</button>
                        <button type="button" class="category-desk__stock-btn"
                            :class="stock === 'stocked' && 'is-active'"
                            @click="stock = 'stocked'">With products</button>
                        <button type="button" class="category-desk__stock-btn"
                            :class="stock === 'empty' && 'is-active'"
                            @click="stock = 'empty'">Empty</button>
                        <button type="button" class="category-desk__stock-btn"
                            :class="stock === 'homepage' && 'is-active'"
                            @click="stock = 'homepage'">Homepage</button>
                        <button type="button" class="category-desk__stock-btn"
                            :class="stock === 'logo' && 'is-active'"
                            @click="stock = 'logo'">With logo</button>
                    </div>
                </div>
            </div>

            <div class="category-desk__grid" role="list" x-ref="list">
                @foreach ($categories as $index => $category)
                    @php
                        $name = is_array($category->name) ? ($category->name['en'] ?? '') : $category->name;
                        $nameAr = is_array($category->name) ? ($category->name['ar'] ?? null) : null;
                        $slug = is_array($category->slug) ? ($category->slug['en'] ?? '') : $category->slug;
                        $count = (int) $category->products_count;
                        $products = $category->products ?? collect();
                        $initial = mb_strtoupper(mb_substr((string) ($name ?: 'C'), 0, 1));
                        $isFeatured = (bool) $category->is_featured;
                        $hasBanner = filled($category->path);
                        $hasLogo = filled($category->logo_url);
                        $previewPhotos = $products
                            ->flatMap(fn ($p) => $p->photos)
                            ->take(3);
                    @endphp
                    <article
                        class="category-desk__card{{ $isFeatured ? ' is-featured' : '' }}{{ $count === 0 ? ' is-empty' : '' }}{{ $hasLogo ? ' has-logo' : '' }}"
                        style="--cd-i: {{ $index }};"
                        role="listitem"
                        data-name="{{ $name }}"
                        data-slug="{{ $slug }}"
                        data-count="{{ $count }}"
                        data-featured="{{ $isFeatured ? '1' : '0' }}"
                        data-logo="{{ $hasLogo ? '1' : '0' }}"
                        x-show="matches($el)"
                        x-transition.opacity.duration.180ms
                        :class="isOpen('{{ $category->id }}') && 'is-open'">

                        <a href="{{ route('admin.categories.edit', $category->id) }}"
                            class="category-desk__cover{{ ! $hasBanner && $hasLogo ? ' is-logo-stage' : '' }}"
                            aria-label="Edit {{ $name }}">
                            @if ($hasBanner)
                                <img
                                    class="category-desk__cover-banner"
                                    src="{{ $category->path }}"
                                    alt="{{ $name }} banner"
                                    width="960"
                                    height="660"
                                    loading="{{ $index < 4 ? 'eager' : 'lazy' }}"
                                    decoding="async"
                                    @if ($index < 2) fetchpriority="high" @endif>
                            @elseif ($hasLogo)
                                <span class="category-desk__cover-logo-stage" aria-hidden="true">
                                    <img
                                        class="category-desk__cover-logo"
                                        src="{{ $category->logo_url }}"
                                        alt=""
                                        width="512"
                                        height="512"
                                        loading="{{ $index < 4 ? 'eager' : 'lazy' }}"
                                        decoding="async"
                                        @if ($index < 2) fetchpriority="high" @endif>
                                </span>
                            @else
                                <span class="category-desk__cover-fallback">
                                    <span class="category-desk__cover-initial">{{ $initial }}</span>
                                    <span class="category-desk__cover-name">{{ $name }}</span>
                                </span>
                            @endif

                            @if ($hasBanner && $hasLogo)
                                <span class="category-desk__logo-plate" aria-hidden="true">
                                    <span class="category-desk__logo-plate-stage">
                                        <img
                                            src="{{ $category->logo_url }}"
                                            alt=""
                                            width="256"
                                            height="256"
                                            loading="lazy"
                                            decoding="async">
                                    </span>
                                </span>
                            @endif

                            <span class="category-desk__cover-veil" aria-hidden="true"></span>

                            <span class="category-desk__badges">
                                @if ($isFeatured)
                                    <span class="category-desk__badge is-homepage">
                                        <span class="category-desk__badge-dot" aria-hidden="true"></span>
                                        Homepage
                                    </span>
                                @elseif ($hasLogo)
                                    <span class="category-desk__badge">Logo rail only</span>
                                @endif
                                @if ($hasLogo)
                                    <span class="category-desk__badge is-logo">Logo</span>
                                @endif
                            </span>

                            <span class="category-desk__cover-count">
                                {{ $count }} {{ $count === 1 ? 'piece' : 'pieces' }}
                            </span>

                            <span class="category-desk__cover-cta">
                                Open studio <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </span>
                        </a>

                        <div class="category-desk__body">
                            <div class="category-desk__identity">
                                <div class="category-desk__identity-row">
                                    @if ($hasLogo)
                                        <a href="{{ route('admin.categories.edit', $category->id) }}"
                                            class="category-desk__mark"
                                            aria-label="{{ $name }} logo — edit category">
                                            <span class="category-desk__mark-stage">
                                                <img
                                                    src="{{ $category->logo_url }}"
                                                    alt="{{ $name }} logo"
                                                    width="192"
                                                    height="192"
                                                    loading="lazy"
                                                    decoding="async">
                                            </span>
                                        </a>
                                    @endif
                                    <div class="category-desk__identity-copy">
                                        <p class="category-desk__kind">
                                            Category
                                            @if ($count > 0)
                                                <span class="category-desk__fill-pill">Stocked</span>
                                            @else
                                                <span class="category-desk__fill-pill is-empty">Empty</span>
                                            @endif
                                        </p>
                                        <h2 class="category-desk__name">
                                            <a href="{{ route('admin.categories.edit', $category->id) }}">{{ $name }}</a>
                                        </h2>
                                        @if ($nameAr && $nameAr !== $name)
                                            <p class="category-desk__name-ar lang-ar" dir="rtl">{{ $nameAr }}</p>
                                        @endif
                                        @if ($category->description)
                                            <p class="category-desk__desc">{{ \Illuminate\Support\Str::limit($category->description, 120) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <dl class="category-desk__stats">
                                <div>
                                    <dt>Products</dt>
                                    <dd>{{ $count }}</dd>
                                </div>
                                <div>
                                    <dt>Position</dt>
                                    <dd>{{ (int) $category->position }}</dd>
                                </div>
                                <div class="category-desk__stats-slug">
                                    <dt>Path</dt>
                                    <dd><code>/{{ $slug }}</code></dd>
                                </div>
                            </dl>

                            @if ($previewPhotos->isNotEmpty())
                                <div class="category-desk__mosaic" aria-hidden="true">
                                    @foreach ($previewPhotos as $photo)
                                        <span class="category-desk__mosaic-cell">
                                            <img src="{{ $photo->url }}" alt="" loading="lazy">
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="category-desk__actions">
                                <button type="button"
                                    class="category-desk__action is-accent"
                                    @click="toggle('{{ $category->id }}')"
                                    :aria-expanded="isOpen('{{ $category->id }}') ? 'true' : 'false'"
                                    aria-controls="category-products-{{ $category->id }}">
                                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                                    <span x-text="isOpen('{{ $category->id }}') ? 'Hide pieces' : 'View pieces'"></span>
                                </button>
                                <a href="{{ route('admin.categories.edit', $category->id) }}"
                                    class="category-desk__action is-primary">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    Edit
                                </a>
                                <a href="{{ route('admin.products.create', ['category_id' => $category->id]) }}"
                                    class="category-desk__action">
                                    <i class="fas fa-plus" aria-hidden="true"></i>
                                    Add
                                </a>
                                @if ($storefrontBase && $slug)
                                    <a href="{{ $storefrontBase }}/collections/{{ $slug }}"
                                        target="_blank" rel="noopener"
                                        class="category-desk__action"
                                        title="View on storefront">
                                        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                        Live
                                    </a>
                                @endif
                                <form action="{{ route('admin.categories.destroy', $category->id) }}"
                                    method="POST"
                                    class="category-desk__delete"
                                    @if ($count > 0)
                                        data-confirm="This category has {{ $count }} product{{ $count === 1 ? '' : 's' }}. To delete it, confirm that those products can stay without a category — you can reassign them later."
                                        data-confirm-title="Delete category?"
                                        data-confirm-confirm="Delete & uncategorize"
                                        data-confirm-choice="Leave products without a category (uncategorized)"
                                        data-confirm-choice-name="uncategorize_products"
                                        data-confirm-choice-checked="1"
                                        data-confirm-choice-required="1"
                                    @else
                                        data-confirm="Delete this empty category? This cannot be undone."
                                        data-confirm-title="Delete category?"
                                        data-confirm-confirm="Delete"
                                    @endif
                                    data-confirm-tone="danger"
                                    data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="category-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="sr-only">Delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div id="category-products-{{ $category->id }}"
                            class="category-desk__drawer"
                            x-show="isOpen('{{ $category->id }}')"
                            x-collapse
                            x-cloak>

                            <div class="category-desk__drawer-head">
                                <div>
                                    <p class="category-desk__drawer-eyebrow">Product gallery</p>
                                    <h3 class="category-desk__drawer-title">
                                        {{ $name }}
                                        <em>{{ $count }} {{ $count === 1 ? 'piece' : 'pieces' }}</em>
                                    </h3>
                                </div>
                                <div class="category-desk__drawer-links">
                                    <a href="{{ route('admin.products.index', ['category' => $category->id]) }}">
                                        Open in products desk
                                    </a>
                                    <a href="{{ route('admin.products.create', ['category_id' => $category->id]) }}">
                                        Add product
                                    </a>
                                </div>
                            </div>

                            @if ($products->isEmpty())
                                <div class="category-desk__drawer-empty">
                                    <p>No products in this category yet.</p>
                                    <a href="{{ route('admin.products.create', ['category_id' => $category->id]) }}"
                                        class="category-desk__primary-btn">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                        Add first product
                                    </a>
                                </div>
                            @else
                                <div class="category-desk__products">
                                    @foreach ($products as $product)
                                        @php
                                            $photo = $product->photos->first();
                                            $productName = $product->translated_name;
                                            $brandName = $product->brand?->translated_name;
                                            $isActive = ($product->status ?? '') === 'active';
                                        @endphp
                                        <a href="{{ route('admin.products.edit', $product->id) }}"
                                            class="category-desk__product">
                                            <span class="category-desk__product-media">
                                                @if ($photo)
                                                    <img src="{{ $photo->url }}" alt="{{ $productName }}" loading="lazy">
                                                @else
                                                    <span class="category-desk__product-fallback">
                                                        {{ mb_strtoupper(mb_substr((string) $productName, 0, 1)) }}
                                                    </span>
                                                @endif
                                                <span class="category-desk__product-status {{ $isActive ? 'is-live' : 'is-off' }}">
                                                    {{ $isActive ? 'Active' : ucfirst((string) ($product->status ?: 'draft')) }}
                                                </span>
                                                @if ($product->featured)
                                                    <span class="category-desk__product-featured">Featured</span>
                                                @endif
                                            </span>
                                            <span class="category-desk__product-body">
                                                @if ($brandName)
                                                    <span class="category-desk__product-brand">{{ $brandName }}</span>
                                                @endif
                                                <span class="category-desk__product-name">{{ $productName }}</span>
                                                <span class="category-desk__product-meta">
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

            <p class="category-desk__no-match" x-show="visibleCount === 0" x-cloak>
                No categories match this search or filter.
            </p>
        @endif
    </div>
@endsection
