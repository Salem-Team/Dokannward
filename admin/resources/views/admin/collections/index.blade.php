@extends('admin.layouts.app')

@section('title', 'Collections')

@section('content')
    @php
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
        $total = $collections->count();
        $liveCount = $collections->where('is_published', true)->count();
        $draftCount = $total - $liveCount;
        $productsTotal = (int) $collections->sum('products_count');
        $completeCount = $collections->filter(
            fn ($c) => (int) $c->products_count > 0
        )->count();
    @endphp

    <div class="brand-studio-page collection-desk"
        x-data="{ filter: 'all' }">

        <header class="collection-desk__hero">
            <div class="collection-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Catalog · Atelier</p>
                <h1 class="brand-studio-page__title">Collections</h1>
                <p class="collection-desk__lede">
                    Curate merchandising groups on the storefront. Each collection owns its cover
                    and direct product members — publishing live to
                    <code>/collections</code>. Categories stay independent classification.
                </p>
            </div>
            <div class="collection-desk__hero-actions">
                @if ($storefrontBase)
                    <a href="{{ $storefrontBase }}/collections" target="_blank" rel="noopener"
                        class="collection-desk__ghost-btn">
                        View live <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                    </a>
                @endif
                <a href="{{ route('admin.collections.create') }}" class="collection-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    New collection
                </a>
            </div>
        </header>
<section class="collection-desk__kpis" aria-label="Collection summary">
            <article class="collection-desk__kpi">
                <p class="collection-desk__kpi-label">Collections</p>
                <p class="collection-desk__kpi-value">{{ $total }}</p>
            </article>
            <article class="collection-desk__kpi">
                <p class="collection-desk__kpi-label">Live on site</p>
                <p class="collection-desk__kpi-value">{{ $liveCount }}</p>
            </article>
            <article class="collection-desk__kpi">
                <p class="collection-desk__kpi-label">Hidden</p>
                <p class="collection-desk__kpi-value">{{ $draftCount }}</p>
            </article>
            <article class="collection-desk__kpi collection-desk__kpi--accent">
                <p class="collection-desk__kpi-label">Products assigned</p>
                <p class="collection-desk__kpi-value">{{ $productsTotal }}</p>
                <p class="collection-desk__kpi-hint">{{ $completeCount }} with members</p>
            </article>
        </section>

        <div class="collection-desk__tip" role="note">
            <span class="collection-desk__tip-mark" aria-hidden="true">01 → 02</span>
            <div>
                <p class="collection-desk__tip-title">How a collection goes live</p>
                <p class="collection-desk__tip-text">
                    Create the collection → assign products from any category.
                    Toggle <strong>Live</strong> when the cover and members are ready for the storefront.
                </p>
            </div>
        </div>

        @if ($collections->isEmpty())
            <div class="collection-desk__empty">
                <div class="collection-desk__empty-frame" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <p class="collection-desk__empty-eyebrow">Start the edit</p>
                <h2 class="collection-desk__empty-title">No collections yet</h2>
                <p class="collection-desk__empty-text">
                    Begin with a title and cover — then assign products from any category so customers can browse the edit.
                </p>
                <a href="{{ route('admin.collections.create') }}" class="collection-desk__primary-btn mt-6">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Create first collection
                </a>
            </div>
        @else
            <div class="collection-desk__toolbar">
                <div class="collection-desk__filters" role="tablist" aria-label="Filter collections">
                    <button type="button" role="tab"
                        class="collection-desk__filter"
                        :class="filter === 'all' && 'is-active'"
                        @click="filter = 'all'"
                        :aria-selected="filter === 'all'">
                        All <em>{{ $total }}</em>
                    </button>
                    <button type="button" role="tab"
                        class="collection-desk__filter"
                        :class="filter === 'live' && 'is-active'"
                        @click="filter = 'live'"
                        :aria-selected="filter === 'live'">
                        Live <em>{{ $liveCount }}</em>
                    </button>
                    <button type="button" role="tab"
                        class="collection-desk__filter"
                        :class="filter === 'draft' && 'is-active'"
                        @click="filter = 'draft'"
                        :aria-selected="filter === 'draft'">
                        Hidden <em>{{ $draftCount }}</em>
                    </button>
                </div>
                <p class="collection-desk__toolbar-note">
                    Ordered by position · click a cover to edit
                </p>
            </div>

            <div class="collection-desk__grid">
                @foreach ($collections as $index => $collection)
                    @php
                        $name = is_array($collection->name) ? ($collection->name['en'] ?? '') : $collection->name;
                        $nameAr = is_array($collection->name) ? ($collection->name['ar'] ?? null) : null;
                        $slug = is_array($collection->slug) ? ($collection->slug['en'] ?? '') : $collection->slug;
                        $isLive = (bool) $collection->is_published;
                        $prods = (int) $collection->products_count;
                        $isComplete = $prods > 0;
                        $filterKey = $isLive ? 'live' : 'draft';
                        $initial = mb_strtoupper(mb_substr((string) ($name ?: 'C'), 0, 1));
                    @endphp
                    <article
                        class="collection-desk__card{{ $isLive ? '' : ' is-draft' }}"
                        style="--cd-i: {{ $index }};"
                        data-status="{{ $filterKey }}"
                        x-show="filter === 'all' || filter === '{{ $filterKey }}'"
                        x-transition.opacity.duration.200ms>

                        <a href="{{ route('admin.collections.edit', $collection->id) }}"
                            class="collection-desk__cover"
                            aria-label="Edit {{ $name }}">
                            @if ($collection->image_url)
                                <img src="{{ $collection->image_url }}" alt="{{ $name }}" loading="lazy">
                            @else
                                <span class="collection-desk__cover-fallback">
                                    <span class="collection-desk__cover-initial">{{ $initial }}</span>
                                    <span class="collection-desk__cover-name">{{ $name }}</span>
                                </span>
                            @endif
                            <span class="collection-desk__cover-veil" aria-hidden="true"></span>
                            <span class="collection-desk__status {{ $isLive ? 'is-live' : 'is-hidden' }}">
                                <span class="collection-desk__status-dot" aria-hidden="true"></span>
                                {{ $isLive ? 'Live' : 'Hidden' }}
                            </span>
                            <span class="collection-desk__cover-cta">
                                Open studio <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </span>
                        </a>

                        <div class="collection-desk__body">
                            <div class="collection-desk__identity">
                                <p class="collection-desk__kind">
                                    Collection
                                    @if ($isComplete)
                                        <span class="collection-desk__complete-pill">Complete</span>
                                    @endif
                                </p>
                                <h2 class="collection-desk__name">
                                    <a href="{{ route('admin.collections.edit', $collection->id) }}">{{ $name }}</a>
                                </h2>
                                @if ($nameAr && $nameAr !== $name)
                                    <p class="collection-desk__name-ar lang-ar" dir="rtl">{{ $nameAr }}</p>
                                @endif
                                @if ($collection->description)
                                    <p class="collection-desk__desc">{{ \Illuminate\Support\Str::limit($collection->description, 110) }}</p>
                                @endif
                            </div>

                            <dl class="collection-desk__stats">
                                <div>
                                    <dt>Products</dt>
                                    <dd>{{ $prods }}</dd>
                                </div>
                                <div class="collection-desk__stats-slug">
                                    <dt>Path</dt>
                                    <dd><code>/{{ $slug }}</code></dd>
                                </div>
                            </dl>

                            @include('admin.collections._roadmap', [
                                'variant' => 'compact',
                                'collection' => $collection,
                            ])

                            <div class="collection-desk__actions">
                                <a href="{{ route('admin.collections.edit', $collection->id) }}"
                                    class="collection-desk__action is-primary">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    Edit
                                </a>
                                <a href="{{ route('admin.products.create', ['collection_id' => $collection->id]) }}"
                                    class="collection-desk__action">
                                    <i class="fas fa-box" aria-hidden="true"></i>
                                    Product
                                </a>
                                @if ($storefrontBase && $slug && $isLive)
                                    <a href="{{ $storefrontBase }}/collections/{{ $slug }}"
                                        target="_blank" rel="noopener"
                                        class="collection-desk__action">
                                        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                        Live
                                    </a>
                                @endif
                                <form action="{{ route('admin.collections.destroy', $collection->id) }}"
                                    method="POST"
                                    class="collection-desk__delete"
                                    data-confirm="Delete this collection? Products and categories stay — only membership is removed." data-confirm-title="Delete collection?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="collection-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="sr-only">Delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="collection-desk__empty-filter"
                x-show="(filter === 'live' && {{ $liveCount }} === 0) || (filter === 'draft' && {{ $draftCount }} === 0)"
                x-cloak>
                Nothing in this filter.
            </p>
        @endif
    </div>
@endsection
