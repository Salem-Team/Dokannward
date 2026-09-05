@extends('admin.layouts.app')

@section('title', 'Products')

@section('content')
    @php
        $pageIds = $products->pluck('id')->values()->all();
        $from = $products->firstItem() ?? 0;
        $to = $products->lastItem() ?? 0;
        $search = request('search');
        $category = request('category');
        $brand = request('brand');
        $hasFilters = filled($search) || filled($category) || filled($brand);
        $matchPct = $catalogTotal > 0
            ? (int) round(($filteredTotal / $catalogTotal) * 100)
            : 0;
        $pageCount = $products->count();
        $inactiveOnPage = max(0, $pageCount - $activeOnPage);
        $activePct = $pageCount > 0
            ? (int) round(($activeOnPage / $pageCount) * 100)
            : 0;
        $dash = $dashboard ?? [
            'active' => 0,
            'inactive' => 0,
            'out_of_stock' => 0,
            'low_stock' => 0,
            'healthy_stock' => 0,
            'total_units' => 0,
        ];
        $livePct = $catalogTotal > 0
            ? (int) round(($dash['active'] / $catalogTotal) * 100)
            : 0;
        $stockDenom = max(1, $catalogTotal);
        $healthyPct = (int) round(($dash['healthy_stock'] / $stockDenom) * 100);
        $lowPct = (int) round(($dash['low_stock'] / $stockDenom) * 100);
        $outPct = (int) round(($dash['out_of_stock'] / $stockDenom) * 100);
        $ringCirc = 2 * M_PI * 42;
        $ringOffset = $ringCirc * (1 - ($livePct / 100));
        $barMax = max(1, $dash['healthy_stock'], $dash['low_stock'], $dash['out_of_stock']);
        $healthyBar = (int) round(($dash['healthy_stock'] / $barMax) * 100);
        $lowBar = (int) round(($dash['low_stock'] / $barMax) * 100);
        $outBar = (int) round(($dash['out_of_stock'] / $barMax) * 100);
        $avgUnits = $catalogTotal > 0
            ? round($dash['total_units'] / $catalogTotal, 1)
            : 0;
        // Donut segments for live vs hidden (SVG stroke-dasharray).
        $donutR = 38;
        $donutC = 2 * M_PI * $donutR;
        $liveLen = $donutC * ($livePct / 100);
        $hiddenLen = max(0, $donutC - $liveLen);
    @endphp

    <div class="brand-studio-page product-desk"
        x-data="{
            selected: [],
            pageIds: @js($pageIds),
            get count() { return this.selected.length },
            get allSelected() {
                return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
            },
            get someSelected() {
                return this.count > 0 && !this.allSelected;
            },
            get selectPct() {
                return this.pageIds.length ? Math.round((this.count / this.pageIds.length) * 100) : 0;
            },
            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.pageIds];
            },
            toggle(id) {
                this.selected = this.selected.includes(id)
                    ? this.selected.filter((value) => value !== id)
                    : [...this.selected, id];
            },
            isChecked(id) {
                return this.selected.includes(id);
            },
            clear() {
                this.selected = [];
            },
            async confirmBulkDelete() {
                if (this.count === 0) return;
                const ok = await (window.dialog?.confirm?.({
                    title: this.count === 1 ? 'Delete 1 product?' : `Delete ${this.count} products?`,
                    message: 'Selected products are removed permanently. Variants and gallery images are deleted. Past order lines stay in history.',
                    confirmLabel: this.count === 1 ? 'Delete product' : 'Delete selected',
                    tone: 'danger',
                    eyebrow: 'Destructive action',
                }) ?? confirm(`Delete ${this.count} product(s)?`));
                if (!ok) return;
                this.$refs.bulkForm.submit();
            },
        }"
        x-init="$watch('someSelected', (value) => {
            if ($refs.selectAll) $refs.selectAll.indeterminate = value;
            if ($refs.selectAllMobile) $refs.selectAllMobile.indeterminate = value;
        })">

        <header class="product-desk__hero">
            <div class="product-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">The house · Catalog</p>
                <h1 class="brand-studio-page__title">Products</h1>
                <p class="product-desk__lede">
                    Curate the full catalog — review covers, pricing, and stock at a glance,
                    then edit a piece or clear several in one action.
                </p>
            </div>
            <div class="product-desk__hero-actions">
                <a href="{{ route('admin.inventory.index') }}" class="product-desk__ghost-btn">
                    <i class="fas fa-warehouse" aria-hidden="true"></i>
                    Inventory
                </a>
                <a href="{{ route('admin.products.create') }}" class="product-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add New Product
                </a>
            </div>
        </header>

        <section class="product-desk__dash" aria-label="Catalog dashboard">
            <article class="product-desk__dash-hero">
                <div class="product-desk__dash-hero-copy">
                    <p class="product-desk__dash-eyebrow">Catalog pulse</p>
                    <p class="product-desk__kpi-label">Total catalog</p>
                    <p class="product-desk__dash-hero-value">{{ number_format($catalogTotal) }}</p>
                    <p class="product-desk__dash-hero-hint">
                        {{ number_format($dash['active']) }} live ·
                        {{ number_format($dash['inactive']) }} hidden ·
                        {{ number_format($dash['total_units']) }} units ·
                        avg {{ $avgUnits }} / piece
                    </p>

                    <div class="product-desk__legend" aria-label="Catalog composition">
                        <span class="product-desk__legend-item is-live">
                            <i></i> Active {{ number_format($dash['active']) }}
                        </span>
                        <span class="product-desk__legend-item is-muted">
                            <i></i> Inactive {{ number_format($dash['inactive']) }}
                        </span>
                        <span class="product-desk__legend-item is-warn">
                            <i></i> Low {{ number_format($dash['low_stock']) }}
                        </span>
                        <span class="product-desk__legend-item is-danger">
                            <i></i> Out {{ number_format($dash['out_of_stock']) }}
                        </span>
                    </div>

                    <div class="product-desk__spark" aria-hidden="true">
                        @php
                            $spark = [
                                ['class' => 'is-live', 'h' => max(8, $livePct)],
                                ['class' => 'is-warn', 'h' => max(8, $lowPct)],
                                ['class' => 'is-danger', 'h' => max(8, $outPct)],
                                ['class' => 'is-healthy', 'h' => max(8, $healthyPct)],
                                ['class' => 'is-live', 'h' => max(8, min(100, (int) round($avgUnits * 4)))],
                                ['class' => 'is-muted', 'h' => max(8, 100 - $livePct)],
                            ];
                        @endphp
                        @foreach ($spark as $bar)
                            <span class="{{ $bar['class'] }}" style="--pd-h: {{ $bar['h'] }}%"></span>
                        @endforeach
                    </div>
                </div>

                <div class="product-desk__dash-visual">
                    <div class="product-desk__dash-ring" aria-hidden="true">
                        <svg viewBox="0 0 100 100" class="product-desk__dash-ring-svg">
                            <circle class="product-desk__dash-ring-track" cx="50" cy="50" r="{{ $donutR }}"></circle>
                            <circle class="product-desk__dash-ring-live" cx="50" cy="50" r="{{ $donutR }}"
                                style="stroke-dasharray: {{ number_format($liveLen, 2, '.', '') }} {{ number_format($donutC, 2, '.', '') }};"></circle>
                            @if ($hiddenLen > 0.5)
                                <circle class="product-desk__dash-ring-hidden" cx="50" cy="50" r="{{ $donutR }}"
                                    style="stroke-dasharray: {{ number_format($hiddenLen, 2, '.', '') }} {{ number_format($donutC, 2, '.', '') }}; stroke-dashoffset: -{{ number_format($liveLen, 2, '.', '') }};"></circle>
                            @endif
                        </svg>
                        <div class="product-desk__dash-ring-label">
                            <strong>{{ $livePct }}%</strong>
                            <span>Live</span>
                        </div>
                    </div>
                    <dl class="product-desk__mini-stats">
                        <div>
                            <dt>Live</dt>
                            <dd>{{ number_format($dash['active']) }}</dd>
                        </div>
                        <div>
                            <dt>Units</dt>
                            <dd>{{ number_format($dash['total_units']) }}</dd>
                        </div>
                    </dl>
                </div>
            </article>

            <div class="product-desk__dash-grid">
                <article class="product-desk__kpi product-desk__kpi--match">
                    <div class="product-desk__kpi-head">
                        <span class="product-desk__kpi-icon" aria-hidden="true"><i class="fas fa-filter"></i></span>
                        <p class="product-desk__kpi-label">Matching filters</p>
                    </div>
                    <div class="product-desk__kpi-main">
                        <p class="product-desk__kpi-value">{{ number_format($filteredTotal) }}</p>
                        <p class="product-desk__kpi-pct">{{ $matchPct }}%</p>
                    </div>
                    <p class="product-desk__kpi-hint">
                        {{ $hasFilters ? 'Of full catalog with current filters' : 'No filters applied — full catalog' }}
                    </p>
                    <div class="product-desk__gauge" aria-hidden="true">
                        <svg viewBox="0 0 120 64" class="product-desk__gauge-svg">
                            <path class="product-desk__gauge-track" d="M10 54 A50 50 0 0 1 110 54"></path>
                            <path class="product-desk__gauge-fill is-match" d="M10 54 A50 50 0 0 1 110 54"
                                style="stroke-dasharray: {{ number_format(157.08 * ($matchPct / 100), 2, '.', '') }} 157.08;"></path>
                        </svg>
                        <span class="product-desk__gauge-caption">{{ $matchPct }}% matched</span>
                    </div>
                </article>

                <article class="product-desk__kpi product-desk__kpi--stock">
                    <div class="product-desk__kpi-head">
                        <span class="product-desk__kpi-icon" aria-hidden="true"><i class="fas fa-chart-bar"></i></span>
                        <p class="product-desk__kpi-label">Stock health</p>
                    </div>
                    <div class="product-desk__kpi-main">
                        <p class="product-desk__kpi-value">{{ number_format($dash['total_units']) }}</p>
                        <p class="product-desk__kpi-pct">units</p>
                    </div>
                    <div class="product-desk__vbars" role="img" aria-label="Stock distribution chart">
                        <div class="product-desk__vbar">
                            <em>{{ number_format($dash['healthy_stock']) }}</em>
                            <span class="product-desk__vbar-col is-healthy" style="--pd-h: {{ $healthyBar }}%"></span>
                            <strong>Healthy</strong>
                        </div>
                        <div class="product-desk__vbar">
                            <em>{{ number_format($dash['low_stock']) }}</em>
                            <span class="product-desk__vbar-col is-low" style="--pd-h: {{ $lowBar }}%"></span>
                            <strong>Low</strong>
                        </div>
                        <div class="product-desk__vbar">
                            <em>{{ number_format($dash['out_of_stock']) }}</em>
                            <span class="product-desk__vbar-col is-out" style="--pd-h: {{ $outBar }}%"></span>
                            <strong>Out</strong>
                        </div>
                    </div>
                    <div class="product-desk__stack-bar" aria-hidden="true">
                        <span class="is-healthy" style="width: {{ $healthyPct }}%"></span>
                        <span class="is-low" style="width: {{ $lowPct }}%"></span>
                        <span class="is-out" style="width: {{ $outPct }}%"></span>
                    </div>
                </article>

                <article class="product-desk__kpi product-desk__kpi--page">
                    <div class="product-desk__kpi-head">
                        <span class="product-desk__kpi-icon" aria-hidden="true"><i class="fas fa-th-list"></i></span>
                        <p class="product-desk__kpi-label">On this page</p>
                    </div>
                    <div class="product-desk__kpi-main">
                        <p class="product-desk__kpi-value">{{ $pageCount }}</p>
                        <p class="product-desk__kpi-pct">{{ $activePct }}%</p>
                    </div>
                    <p class="product-desk__kpi-hint">
                        {{ $activeOnPage }} active · {{ $inactiveOnPage }} inactive
                        @if ($filteredTotal > 0)
                            · {{ $from }}–{{ $to }} of {{ number_format($filteredTotal) }}
                        @endif
                    </p>
                    <div class="product-desk__split" aria-hidden="true">
                        <div class="product-desk__split-row">
                            <span>Active</span>
                            <div class="product-desk__split-track">
                                <i class="is-live" style="width: {{ $activePct }}%"></i>
                            </div>
                            <em>{{ $activeOnPage }}</em>
                        </div>
                        <div class="product-desk__split-row">
                            <span>Inactive</span>
                            <div class="product-desk__split-track">
                                <i class="is-muted" style="width: {{ $pageCount ? (int) round(($inactiveOnPage / $pageCount) * 100) : 0 }}%"></i>
                            </div>
                            <em>{{ $inactiveOnPage }}</em>
                        </div>
                    </div>
                </article>

                <article class="product-desk__kpi product-desk__kpi--select"
                    :class="count > 0 ? 'product-desk__kpi--accent' : ''">
                    <div class="product-desk__kpi-head">
                        <span class="product-desk__kpi-icon" aria-hidden="true"><i class="fas fa-check-double"></i></span>
                        <p class="product-desk__kpi-label">Selected</p>
                    </div>
                    <div class="product-desk__kpi-main">
                        <p class="product-desk__kpi-value" x-text="count">0</p>
                        <p class="product-desk__kpi-pct" x-text="selectPct + '%'">0%</p>
                    </div>
                    <p class="product-desk__kpi-hint" x-text="count > 0 ? 'Ready for bulk delete' : 'None selected on this page'"></p>
                    <div class="product-desk__gauge" aria-hidden="true">
                        <svg viewBox="0 0 120 64" class="product-desk__gauge-svg">
                            <path class="product-desk__gauge-track" d="M10 54 A50 50 0 0 1 110 54"></path>
                            <path class="product-desk__gauge-fill is-select" d="M10 54 A50 50 0 0 1 110 54"
                                :style="'stroke-dasharray:' + (157.08 * (selectPct / 100)) + ' 157.08'"></path>
                        </svg>
                        <span class="product-desk__gauge-caption" x-text="selectPct + '% of page'"></span>
                    </div>
                </article>
            </div>
        </section>

        @php
            $topOrdered = $topOrderedProducts ?? collect();
            $topUnitsMax = max(1, (int) $topOrdered->max('units_ordered'));
            $topOrdersTotal = (int) $topOrdered->sum('orders_count');
            $topUnitsTotal = (int) $topOrdered->sum('units_ordered');
            $topRevenueTotal = (float) $topOrdered->sum('order_revenue');
        @endphp

        <section class="product-desk__leaders" aria-label="Most ordered products">
            <header class="product-desk__leaders-head">
                <div>
                    <p class="product-desk__dash-eyebrow product-desk__dash-eyebrow--ink">Demand desk</p>
                    <h2 class="product-desk__leaders-title">Most ordered products</h2>
                    <p class="product-desk__leaders-lede">
                        Ranked by units sold across non-cancelled orders — demand signal for restocking and merchandising.
                    </p>
                </div>
                @if ($topOrdered->isNotEmpty())
                    <dl class="product-desk__leaders-summary">
                        <div>
                            <dt>Top pieces</dt>
                            <dd>{{ $topOrdered->count() }}</dd>
                        </div>
                        <div>
                            <dt>Orders</dt>
                            <dd>{{ number_format($topOrdersTotal) }}</dd>
                        </div>
                        <div>
                            <dt>Units</dt>
                            <dd>{{ number_format($topUnitsTotal) }}</dd>
                        </div>
                        <div>
                            <dt>Revenue</dt>
                            <dd>LE {{ number_format($topRevenueTotal, 0) }}</dd>
                        </div>
                    </dl>
                @endif
            </header>

            @if ($topOrdered->isEmpty())
                <div class="product-desk__leaders-empty">
                    <p class="product-desk__empty-eyebrow">No demand yet</p>
                    <h3 class="product-desk__empty-title">No ordered products to rank</h3>
                    <p class="product-desk__empty-text">
                        Once customers place orders, the top performers will appear here with units, order count, and revenue.
                    </p>
                </div>
            @else
                <div class="product-desk__leaders-board">
                    <ol class="product-desk__leaders-list">
                        @foreach ($topOrdered as $index => $leader)
                            @php
                                $leaderName = $leader->getTranslatedNameAttribute();
                                $leaderCover = $leader->coverPhoto()?->url;
                                $leaderBrand = $leader->brand?->getTranslatedNameAttribute();
                                $units = (int) ($leader->units_ordered ?? 0);
                                $orders = (int) ($leader->orders_count ?? 0);
                                $revenue = (float) ($leader->order_revenue ?? 0);
                                $share = (int) round(($units / $topUnitsMax) * 100);
                                $rank = $index + 1;
                                $initials = collect(explode(' ', $leaderName))
                                    ->filter()
                                    ->map(fn ($w) => mb_substr($w, 0, 1))
                                    ->take(2)
                                    ->join('');
                            @endphp
                            <li class="product-desk__leader{{ $rank <= 3 ? ' is-podium' : '' }}" style="--pd-i: {{ $index }}; --pd-share: {{ $share }}%;">
                                <span class="product-desk__leader-rank" aria-hidden="true">{{ str_pad((string) $rank, 2, '0', STR_PAD_LEFT) }}</span>
                                <a href="{{ route('admin.products.show', $leader->id) }}" class="product-desk__leader-media" aria-label="View {{ $leaderName }}">
                                    @if ($leaderCover)
                                        <img src="{{ $leaderCover }}" alt="" class="product-desk__cover" loading="lazy" decoding="async">
                                    @else
                                        <span class="product-desk__cover product-desk__cover--fallback">{{ $initials ?: '—' }}</span>
                                    @endif
                                </a>
                                <div class="product-desk__leader-body">
                                    <div class="product-desk__leader-meta">
                                        @if ($leaderBrand)
                                            <p class="product-desk__leader-brand">{{ $leaderBrand }}</p>
                                        @endif
                                        <h3 class="product-desk__leader-name">
                                            <a href="{{ route('admin.products.show', $leader->id) }}">{{ $leaderName }}</a>
                                        </h3>
                                        <p class="product-desk__leader-facts">
                                            <span>{{ number_format($orders) }} {{ $orders === 1 ? 'order' : 'orders' }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span>{{ number_format($units) }} units</span>
                                            @if ($leader->sku)
                                                <span aria-hidden="true">·</span>
                                                <code>{{ $leader->sku }}</code>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="product-desk__leader-meter" aria-hidden="true">
                                        <span style="width: {{ $share }}%"></span>
                                    </div>
                                </div>
                                <div class="product-desk__leader-stats">
                                    <p class="product-desk__leader-units">
                                        <strong>{{ number_format($units) }}</strong>
                                        <span>units</span>
                                    </p>
                                    <p class="product-desk__leader-revenue">LE {{ number_format($revenue, 0) }}</p>
                                    <a href="{{ route('admin.products.edit', $leader->id) }}" class="product-desk__leader-edit">
                                        Edit <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <aside class="product-desk__leaders-chart" aria-label="Demand chart">
                        <p class="product-desk__kpi-label">Demand share</p>
                        <p class="product-desk__leaders-chart-hint">Relative units among top performers</p>
                        <div class="product-desk__leaders-bars">
                            @foreach ($topOrdered->take(6) as $index => $leader)
                                @php
                                    $units = (int) ($leader->units_ordered ?? 0);
                                    $share = (int) round(($units / $topUnitsMax) * 100);
                                    $shortName = \Illuminate\Support\Str::limit($leader->getTranslatedNameAttribute(), 18);
                                @endphp
                                <div class="product-desk__leaders-bar" style="--pd-i: {{ $index }};">
                                    <span class="product-desk__leaders-bar-rank">{{ $index + 1 }}</span>
                                    <div class="product-desk__leaders-bar-track">
                                        <span style="width: {{ max(8, $share) }}%"></span>
                                    </div>
                                    <div class="product-desk__leaders-bar-meta">
                                        <strong title="{{ $leader->getTranslatedNameAttribute() }}">{{ $shortName }}</strong>
                                        <em>{{ number_format($units) }}</em>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>
            @endif
        </section>

        <form method="GET" action="{{ route('admin.products.index') }}" class="product-desk__filters">
            <label class="product-desk__search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ $search }}"
                    placeholder="Search by name, SKU, or description…" autocomplete="off">
            </label>

            <select name="category" class="product-desk__select" aria-label="Filter by category">
                <option value="">All Categories</option>
                @foreach ($categories ?? [] as $id => $name)
                    <option value="{{ $id }}" @selected((string) $category === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>

            <select name="brand" class="product-desk__select" aria-label="Filter by brand">
                <option value="">All Brands</option>
                @foreach ($brands ?? [] as $id => $name)
                    <option value="{{ $id }}" @selected((string) $brand === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>

            <button type="submit" class="product-desk__primary-btn product-desk__filter-submit">
                <i class="fas fa-filter" aria-hidden="true"></i>
                Apply Filters
            </button>
            <a href="{{ route('admin.products.index') }}" class="product-desk__ghost-btn">Clear</a>
        </form>

        @if ($products->isEmpty())
            <div class="product-desk__empty">
                <div class="product-desk__empty-frame" aria-hidden="true">
                    <i class="fas fa-box-open"></i>
                </div>
                <p class="product-desk__empty-eyebrow">Catalog desk</p>
                <h2 class="product-desk__empty-title">
                    {{ $hasFilters ? 'No products match these filters' : 'No products found' }}
                </h2>
                <p class="product-desk__empty-text">
                    {{ $hasFilters
                        ? 'Try widening your search or clearing filters to see the full catalog.'
                        : 'Start by adding your first product to the house catalog.' }}
                </p>
                @if ($hasFilters)
                    <a href="{{ route('admin.products.index') }}" class="product-desk__primary-btn">
                        Clear filters
                    </a>
                @else
                    <a href="{{ route('admin.products.create') }}" class="product-desk__primary-btn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Add Product
                    </a>
                @endif
            </div>
        @else
            <div class="product-desk__results-bar">
                <div class="product-desk__results-left">
                    <label class="product-desk__check product-desk__check--bar" title="Select all on this page">
                        <input type="checkbox"
                            x-ref="selectAllMobile"
                            :checked="allSelected"
                            @change="toggleAll()"
                            @click.stop>
                        <span>Select page</span>
                    </label>
                    <p class="product-desk__results-count">
                        Showing
                        <strong>{{ $from }}–{{ $to }}</strong>
                        of
                        <strong>{{ number_format($filteredTotal) }}</strong>
                        products
                    </p>
                </div>
                <button type="button"
                    class="product-desk__clear-sel"
                    x-show="count > 0"
                    x-cloak
                    @click="clear()">
                    Clear selection
                </button>
            </div>

            {{-- Desktop / wide table --}}
            <div class="product-desk__table-wrap">
                <table class="product-desk__table">
                    <thead>
                        <tr>
                            <th scope="col" class="product-desk__th-check">
                                <label class="product-desk__check" title="Select all on this page">
                                    <input type="checkbox"
                                        x-ref="selectAll"
                                        :checked="allSelected"
                                        @change="toggleAll()"
                                        @click.stop>
                                    <span class="sr-only">Select all on this page</span>
                                </label>
                            </th>
                            <th scope="col" class="product-desk__th-media">Piece</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Category</th>
                            <th scope="col">Brand</th>
                            <th scope="col" class="is-numeric">Price</th>
                            <th scope="col" class="is-numeric">Stock</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $index => $product)
                            @php
                                $cover = $product->coverPhoto();
                                $imageUrl = $cover?->url;
                                $productName = $product->getTranslatedNameAttribute();
                                $productDesc = $product->getTranslatedDescriptionAttribute();
                                $initials = collect(explode(' ', $productName))
                                    ->filter()
                                    ->map(fn ($w) => mb_substr($w, 0, 1))
                                    ->take(2)
                                    ->join('');
                                $stock = (int) ($product->variants_stock_sum ?? 0);
                                $stockLevel = match (true) {
                                    $stock === 0 => 'out',
                                    $stock <= 10 => 'low',
                                    $stock <= 30 => 'medium',
                                    default => 'healthy',
                                };
                                $listPrice = (float) ($product->price ?? 0);
                                $listCompare = (float) ($product->price_max ?? $listPrice);
                                $listOnSale = $listCompare > $listPrice;
                                $listSavePct = $listOnSale
                                    ? (int) round((($listCompare - $listPrice) / $listCompare) * 100)
                                    : 0;
                                $isActive = $product->status === 'active';
                                $categoryName = $product->category ? $product->category->getTranslatedNameAttribute() : '—';
                                $brandName = $product->brand ? $product->brand->getTranslatedNameAttribute() : '—';
                            @endphp
                            <tr class="product-desk__row"
                                style="--pd-i: {{ $index }};"
                                :class="isChecked('{{ $product->id }}') ? 'is-selected' : ''">
                                <td class="product-desk__td-check">
                                    <label class="product-desk__check">
                                        <input type="checkbox"
                                            :checked="isChecked('{{ $product->id }}')"
                                            @change="toggle('{{ $product->id }}')"
                                            @click.stop>
                                        <span class="sr-only">Select {{ $productName }}</span>
                                    </label>
                                </td>
                                <td class="product-desk__piece">
                                    <div class="product-desk__piece-cell">
                                        <a href="{{ route('admin.products.show', $product->id) }}"
                                            class="product-desk__media"
                                            aria-label="View {{ $productName }}">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}"
                                                    alt="{{ $productName }}"
                                                    class="product-desk__cover"
                                                    loading="lazy"
                                                    decoding="async">
                                            @else
                                                <span class="product-desk__cover product-desk__cover--fallback" aria-hidden="true">
                                                    <span class="product-desk__cover-initial">{{ $initials ?: '—' }}</span>
                                                </span>
                                            @endif
                                            <span class="product-desk__media-veil" aria-hidden="true"></span>
                                            <span class="product-desk__media-hint">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                            </span>
                                        </a>
                                        <div class="product-desk__piece-meta">
                                            <p class="product-desk__name">
                                                <a href="{{ route('admin.products.show', $product->id) }}">
                                                    {{ $productName }}
                                                </a>
                                            </p>
                                            @if ($productDesc)
                                                <p class="product-desk__desc">{{ Str::limit($productDesc, 72) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="SKU">
                                    <code class="product-desk__sku">{{ $product->sku }}</code>
                                </td>
                                <td data-label="Category">
                                    <span class="product-desk__meta-text">{{ $categoryName }}</span>
                                </td>
                                <td data-label="Brand">
                                    <span class="product-desk__brand">{{ $brandName }}</span>
                                </td>
                                <td class="is-numeric" data-label="Price">
                                    <span class="product-desk__price">
                                        <span class="product-desk__price-now">LE {{ number_format($listPrice, 2) }}</span>
                                        @if ($listOnSale)
                                            <span class="product-desk__price-was">LE {{ number_format($listCompare, 2) }}</span>
                                            <span class="product-desk__price-badge">−{{ $listSavePct }}%</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="is-numeric" data-label="Stock">
                                    <span class="product-desk__stock is-{{ $stockLevel }}">
                                        {{ number_format($stock) }}
                                        <em>units</em>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <span class="product-desk__status {{ $isActive ? 'is-active' : 'is-inactive' }}">
                                        <span class="product-desk__status-dot" aria-hidden="true"></span>
                                        {{ $isActive ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="product-desk__actions" data-label="Actions">
                                    <div class="product-desk__action-group">
                                        <a href="{{ route('admin.products.show', $product->id) }}"
                                            class="product-desk__action" title="View">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                            <span class="sr-only">View</span>
                                        </a>
                                        <a href="{{ route('admin.products.edit', $product->id) }}"
                                            class="product-desk__action" title="Edit">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                            <span class="sr-only">Edit</span>
                                        </a>
                                        <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST"
                                            data-confirm="Delete this product permanently? Variants and gallery images are removed. Past order lines stay in history."
                                            data-confirm-title="Delete product?"
                                            data-confirm-confirm="Delete product"
                                            data-confirm-tone="danger"
                                            data-confirm-eyebrow="Destructive action">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="product-desk__action is-danger" title="Delete">
                                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                                <span class="sr-only">Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile / tablet cards — full product image --}}
            <div class="product-desk__cards" aria-label="Product cards">
                @foreach ($products as $index => $product)
                    @php
                        $cover = $product->coverPhoto();
                        $imageUrl = $cover?->url;
                        $productName = $product->getTranslatedNameAttribute();
                        $productDesc = $product->getTranslatedDescriptionAttribute();
                        $initials = collect(explode(' ', $productName))
                            ->filter()
                            ->map(fn ($w) => mb_substr($w, 0, 1))
                            ->take(2)
                            ->join('');
                        $stock = (int) ($product->variants_stock_sum ?? 0);
                        $stockLevel = match (true) {
                            $stock === 0 => 'out',
                            $stock <= 10 => 'low',
                            $stock <= 30 => 'medium',
                            default => 'healthy',
                        };
                        $listPrice = (float) ($product->price ?? 0);
                        $listCompare = (float) ($product->price_max ?? $listPrice);
                        $listOnSale = $listCompare > $listPrice;
                        $listSavePct = $listOnSale
                            ? (int) round((($listCompare - $listPrice) / $listCompare) * 100)
                            : 0;
                        $isActive = $product->status === 'active';
                        $categoryName = $product->category ? $product->category->getTranslatedNameAttribute() : '—';
                        $brandName = $product->brand ? $product->brand->getTranslatedNameAttribute() : '—';
                    @endphp
                    <article class="product-desk__card"
                        style="--pd-i: {{ $index }};"
                        :class="isChecked('{{ $product->id }}') ? 'is-selected' : ''">
                        <div class="product-desk__card-top">
                            <label class="product-desk__check">
                                <input type="checkbox"
                                    :checked="isChecked('{{ $product->id }}')"
                                    @change="toggle('{{ $product->id }}')"
                                    @click.stop>
                                <span class="sr-only">Select {{ $productName }}</span>
                            </label>
                            <span class="product-desk__status {{ $isActive ? 'is-active' : 'is-inactive' }}">
                                <span class="product-desk__status-dot" aria-hidden="true"></span>
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <a href="{{ route('admin.products.show', $product->id) }}"
                            class="product-desk__card-media"
                            aria-label="View {{ $productName }}">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}"
                                    alt="{{ $productName }}"
                                    class="product-desk__cover"
                                    loading="lazy"
                                    decoding="async">
                            @else
                                <span class="product-desk__cover product-desk__cover--fallback" aria-hidden="true">
                                    <span class="product-desk__cover-initial">{{ $initials ?: '—' }}</span>
                                </span>
                            @endif
                        </a>

                        <div class="product-desk__card-body">
                            <p class="product-desk__brand">{{ $brandName }}</p>
                            <h2 class="product-desk__name">
                                <a href="{{ route('admin.products.show', $product->id) }}">{{ $productName }}</a>
                            </h2>
                            @if ($productDesc)
                                <p class="product-desk__desc">{{ Str::limit($productDesc, 90) }}</p>
                            @endif

                            <dl class="product-desk__card-facts">
                                <div>
                                    <dt>SKU</dt>
                                    <dd><code class="product-desk__sku">{{ $product->sku }}</code></dd>
                                </div>
                                <div>
                                    <dt>Category</dt>
                                    <dd>{{ $categoryName }}</dd>
                                </div>
                                <div>
                                    <dt>Price</dt>
                                    <dd>
                                        <span class="product-desk__price">
                                            <span class="product-desk__price-now">LE {{ number_format($listPrice, 2) }}</span>
                                            @if ($listOnSale)
                                                <span class="product-desk__price-was">LE {{ number_format($listCompare, 2) }}</span>
                                                <span class="product-desk__price-badge">−{{ $listSavePct }}%</span>
                                            @endif
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Stock</dt>
                                    <dd>
                                        <span class="product-desk__stock is-{{ $stockLevel }}">
                                            {{ number_format($stock) }} <em>units</em>
                                        </span>
                                    </dd>
                                </div>
                            </dl>

                            <div class="product-desk__action-group">
                                <a href="{{ route('admin.products.show', $product->id) }}" class="product-desk__action" title="View">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                    <span>View</span>
                                </a>
                                <a href="{{ route('admin.products.edit', $product->id) }}" class="product-desk__action" title="Edit">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    <span>Edit</span>
                                </a>
                                <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST"
                                    data-confirm="Delete this product permanently? Variants and gallery images are removed. Past order lines stay in history."
                                    data-confirm-title="Delete product?"
                                    data-confirm-confirm="Delete product"
                                    data-confirm-tone="danger"
                                    data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="product-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                        <span>Delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($products->hasPages())
                <div class="product-desk__pagination">
                    {{ $products->links() }}
                </div>
            @endif
        @endif

        <form x-ref="bulkForm" method="POST" action="{{ route('admin.products.bulk-destroy') }}" class="hidden">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>

        <div x-show="count > 0"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-3"
            class="product-desk__bulk">
            <div class="product-desk__bulk-inner">
                <div class="product-desk__bulk-copy">
                    <p class="product-desk__bulk-title">
                        <span x-text="count"></span>
                        <span x-text="count === 1 ? 'product selected' : 'products selected'"></span>
                    </p>
                    <p class="product-desk__bulk-hint">
                        Delete removes gallery images and variants. Order history is kept.
                    </p>
                </div>
                <div class="product-desk__bulk-actions">
                    <button type="button" class="product-desk__ghost-btn" @click="clear()">
                        Cancel
                    </button>
                    <button type="button" class="product-desk__danger-btn" @click="confirmBulkDelete()">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        Delete selected
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
