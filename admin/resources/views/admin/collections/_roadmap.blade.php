{{--
    Collection merchandising roadmap:
    Collection → Products
    Variants: compact | full | setup
--}}
@php
    $variant = $variant ?? 'compact';
    $collection = $collection ?? null;
    $productsCount = (int) ($productsCount ?? ($collection->products_count ?? $collection?->products?->count() ?? 0));
    $products = collect($collection?->products ?? []);
    $name = $collection
        ? (is_array($collection->name) ? ($collection->name['en'] ?? 'Collection') : ($collection->name ?: 'Collection'))
        : ($name ?? 'New collection');
    $collectionId = $collection?->id;
    $isPublished = (bool) ($collection?->is_published ?? false);
    $slug = $collection
        ? (is_array($collection->slug) ? ($collection->slug['en'] ?? '') : ($collection->slug ?: ''))
        : '';

    $isSetup = $variant === 'setup';
    $stageProducts = ! $isSetup && $productsCount > 0;
    $doneStages = $isSetup ? 0 : (1 + (int) $stageProducts);
    $progressPct = $isSetup ? 18 : (int) round(($doneStages / 2) * 100);

    if ($isSetup) {
        $nextLabel = 'Save this collection to add products';
        $nextHref = null;
        $statusLabel = 'Getting started';
    } elseif (! $stageProducts) {
        $nextLabel = 'Add your first product';
        $nextHref = $collectionId ? route('admin.products.create', ['collection_id' => $collectionId]) : null;
        $statusLabel = 'Needs products';
    } else {
        $nextLabel = 'Merchandising ready';
        $nextHref = $collectionId ? route('admin.collections.edit', $collectionId) : null;
        $statusLabel = 'Complete';
    }

    $step1Class = $isSetup ? 'is-current' : 'is-done';
    $step2Class = $isSetup ? 'is-todo' : ($stageProducts ? 'is-done' : 'is-current');
@endphp

@if ($variant === 'compact')
    <div class="crm crm--compact" style="--crm-progress: {{ max(8, $progressPct) }}%;" aria-label="Merchandising roadmap for {{ $name }}">
        <div class="crm__top">
            <span class="crm__kicker">Roadmap</span>
            <span class="crm__status {{ $doneStages === 2 ? 'is-complete' : '' }}">{{ $statusLabel }}</span>
        </div>

        <div class="crm__journey" aria-hidden="true">
            <span class="crm__spine">
                <span class="crm__spine-fill"></span>
            </span>
            <ol class="crm__nodes">
                <li class="crm__node {{ $step1Class }}">
                    <span class="crm__dot"><i class="fas fa-check"></i></span>
                    <span class="crm__caption">
                        <strong>Collection</strong>
                        <em>Ready</em>
                    </span>
                </li>
                <li class="crm__node {{ $step2Class }}">
                    <span class="crm__dot">{{ $stageProducts ? $productsCount : '2' }}</span>
                    <span class="crm__caption">
                        <strong>Products</strong>
                        <em>{{ $productsCount }} {{ $productsCount === 1 ? 'piece' : 'pieces' }}</em>
                    </span>
                </li>
            </ol>
        </div>

        @if ($nextHref && $doneStages < 2)
            <a href="{{ $nextHref }}" class="crm__next" onclick="event.stopPropagation()">
                <span>{{ $nextLabel }}</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        @else
            <p class="crm__next crm__next--done">
                <i class="fas fa-check-circle"></i>
                <span>{{ $nextLabel }}</span>
            </p>
        @endif
    </div>

@elseif ($variant === 'setup')
    <aside class="crm crm--panel crm--setup" style="--crm-progress: {{ $progressPct }}%;" aria-label="Collection setup roadmap">
        <header class="crm__header">
            <div>
                <p class="crm__eyebrow">Merchandising roadmap</p>
                <h3 class="crm__title">Build in two clear stages</h3>
                <p class="crm__lede">Collections are merchandising groups — they can mix any categories.</p>
            </div>
            <div class="crm__meter" aria-hidden="true">
                <span class="crm__meter-ring">
                    <span class="crm__meter-value">1</span>
                </span>
                <span class="crm__meter-label">of 2</span>
            </div>
        </header>

        <div class="crm__journey crm__journey--panel">
            <span class="crm__spine"><span class="crm__spine-fill"></span></span>
            <ol class="crm__nodes">
                <li class="crm__node is-current">
                    <span class="crm__dot">01</span>
                    <span class="crm__caption">
                        <strong>Collection</strong>
                        <em>Title, cover, visibility — you are here</em>
                    </span>
                </li>
                <li class="crm__node is-todo">
                    <span class="crm__dot">02</span>
                    <span class="crm__caption">
                        <strong>Products</strong>
                        <em>Assign pieces from any category</em>
                    </span>
                </li>
            </ol>
        </div>
    </aside>

@else
    <section class="crm crm--panel crm--full" style="--crm-progress: {{ max(14, $progressPct) }}%;" aria-label="Merchandising roadmap for {{ $name }}">
        <header class="crm__header">
            <div>
                <p class="crm__eyebrow">Merchandising roadmap</p>
                <h2 class="crm__title">{{ $name }}</h2>
                <p class="crm__path" aria-label="Workflow path">
                    <span>Collection</span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    <span class="{{ $stageProducts ? 'is-on' : '' }}">Products</span>
                </p>
            </div>
            <div class="crm__header-aside">
                <span class="crm__status {{ $doneStages === 2 ? 'is-complete' : '' }}">{{ $statusLabel }}</span>
                <div class="crm__meter" aria-hidden="true">
                    <span class="crm__meter-ring">
                        <span class="crm__meter-value">{{ $progressPct }}</span>
                    </span>
                    <span class="crm__meter-label">%</span>
                </div>
            </div>
        </header>

        <div class="crm__journey crm__journey--panel">
            <span class="crm__spine"><span class="crm__spine-fill"></span></span>
            <ol class="crm__nodes">
                <li class="crm__node {{ $step1Class }}">
                    <span class="crm__dot"><i class="fas fa-check"></i></span>
                    <span class="crm__caption">
                        <strong>Collection</strong>
                        <em>{{ $isPublished ? 'Live on storefront' : 'Saved · currently hidden' }}</em>
                    </span>
                </li>
                <li class="crm__node {{ $step2Class }}">
                    <span class="crm__dot">{{ $stageProducts ? $productsCount : '02' }}</span>
                    <span class="crm__caption">
                        <strong>Products</strong>
                        <em>{{ $productsCount }} {{ $productsCount === 1 ? 'piece assigned' : 'pieces assigned' }}</em>
                    </span>
                </li>
            </ol>
        </div>

        <div class="crm__tree">
            <article class="crm__root">
                <div class="crm__root-media">
                    @if ($collection?->image_url)
                        <img src="{{ $collection->image_url }}" alt="" loading="lazy">
                    @else
                        <span class="crm__root-fallback">{{ mb_strtoupper(mb_substr((string) $name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="crm__root-body">
                    <p class="crm__kind">Collection</p>
                    <h3 class="crm__root-name">{{ $name }}</h3>
                    <p class="crm__root-meta">
                        @if ($slug)
                            <code>/{{ $slug }}</code>
                            <span aria-hidden="true">·</span>
                        @endif
                        <span>{{ $productsCount }} {{ $productsCount === 1 ? 'product' : 'products' }}</span>
                    </p>
                </div>
            </article>

            <div class="crm__branches" role="list">
                @forelse ($products->take(8) as $index => $product)
                    @php
                        $productName = is_array($product->name) ? ($product->name['en'] ?? 'Product') : ($product->name ?: 'Product');
                        $categoryName = $product->category?->translated_name;
                        $thumb = $product->photos->first()?->url ?? null;
                    @endphp
                    <article class="crm__branch" role="listitem" style="--crm-i: {{ $index }};">
                        <span class="crm__branch-join" aria-hidden="true"></span>
                        <div class="crm__branch-card">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" class="crm__thumb" loading="lazy">
                            @else
                                <span class="crm__thumb is-empty">{{ mb_strtoupper(mb_substr((string) $productName, 0, 1)) }}</span>
                            @endif
                            <div class="crm__branch-body">
                                <p class="crm__kind">Product</p>
                                <h4 class="crm__branch-name">{{ $productName }}</h4>
                                <p class="crm__branch-count">
                                    {{ $categoryName ?: 'Uncategorized' }}
                                </p>
                            </div>
                            <div class="crm__branch-actions">
                                <a href="{{ route('admin.products.edit', $product->id) }}" class="crm__icon-btn" title="Edit product">
                                    <i class="fas fa-pen"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="crm__empty">
                        <div class="crm__empty-mark" aria-hidden="true">02</div>
                        <div>
                            <p class="crm__empty-title">No products in this collection yet</p>
                            <p class="crm__empty-text">
                                Add products from any category — a collection is a merchandising group, not a folder of categories.
                            </p>
                            @if ($collectionId)
                                <a href="{{ route('admin.products.create', ['collection_id' => $collectionId]) }}" class="crm__cta">
                                    <i class="fas fa-box"></i>
                                    Add first product
                                </a>
                            @endif
                        </div>
                    </div>
                @endforelse

                @if ($products->isNotEmpty() && $collectionId)
                    <div class="crm__branch crm__branch--add" style="--crm-i: {{ min(8, $products->count()) }};">
                        <span class="crm__branch-join" aria-hidden="true"></span>
                        <a href="{{ route('admin.products.create', ['collection_id' => $collectionId]) }}" class="crm__add">
                            <span class="crm__add-icon"><i class="fas fa-plus"></i></span>
                            <span>
                                <strong>Add another product</strong>
                                <em>From any category</em>
                            </span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        @if ($nextHref && $doneStages < 2)
            <footer class="crm__footer">
                <a href="{{ $nextHref }}" class="crm__cta">
                    <span>{{ $nextLabel }}</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </footer>
        @elseif ($doneStages === 2)
            <footer class="crm__footer">
                <p class="crm__complete">
                    <i class="fas fa-check-circle"></i>
                    Merchandising ready — collection and products are connected.
                </p>
            </footer>
        @endif
    </section>
@endif
