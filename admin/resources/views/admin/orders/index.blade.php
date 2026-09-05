@extends('admin.layouts.app')

@section('title', 'Orders')

@section('content')
    @php
        $status = request('status');
        $refund = request('refund');
        $search = request('search');
        $openIds = collect([$openOrderId ?? null])->filter()->values()->all();
        $pageIds = $orders->pluck('id')->values()->all();
        $pipeline = $pipeline ?? [
            'all' => $orders->total(),
            'pending' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
            'refunded' => 0,
        ];
        $statusChips = [
            'all' => 'All',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
    @endphp

    <div class="brand-studio-page orders-desk"
        x-data="{
            open: @js($openIds),
            selected: [],
            pageIds: @js($pageIds),
            get count() { return this.selected.length },
            get allSelected() {
                return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
            },
            get someSelected() {
                return this.count > 0 && !this.allSelected;
            },
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
            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.pageIds];
            },
            toggleSelect(id) {
                this.selected = this.selected.includes(id)
                    ? this.selected.filter((value) => value !== id)
                    : [...this.selected, id];
            },
            isChecked(id) {
                return this.selected.includes(id);
            },
            clearSelection() {
                this.selected = [];
            },
            async confirmBulkDelete() {
                if (this.count === 0) return;
                const ok = await (window.dialog?.confirm?.({
                    title: this.count === 1 ? 'Delete 1 order?' : `Delete ${this.count} orders?`,
                    message: 'Selected orders are removed permanently. Stock is restored unless the order was already cancelled or refunded.',
                    confirmLabel: this.count === 1 ? 'Delete order' : 'Delete selected',
                    tone: 'danger',
                    eyebrow: 'Destructive action',
                }) ?? confirm(`Delete ${this.count} order(s)?`));
                if (!ok) return;
                this.$refs.bulkForm.submit();
            },
        }"
        x-init="$watch('someSelected', (value) => {
            if ($refs.selectAll) $refs.selectAll.indeterminate = value;
        })">

        <header class="orders-desk__hero">
            <div class="orders-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Commerce · Operations</p>
                <h1 class="brand-studio-page__title">Orders</h1>
                <p class="orders-desk__lede">
                    Website checkouts and manual phone orders in one desk.
                    Select orders to delete, or expand any row to inspect its products.
                </p>
            </div>
            <div class="orders-desk__hero-actions">
                <a href="{{ route('admin.orders.export', request()->query()) }}"
                    class="orders-desk__ghost-btn">
                    <i class="fas fa-file-export" aria-hidden="true"></i>
                    Export CSV
                </a>
                <a href="{{ route('admin.orders.create') }}" class="orders-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Create order
                </a>
            </div>
        </header>

        <section class="orders-desk__kpis" aria-label="Order pipeline summary">
            <article class="orders-desk__kpi">
                <p class="orders-desk__kpi-label">In view</p>
                <p class="orders-desk__kpi-value">{{ $orders->total() }}</p>
            </article>
            <article class="orders-desk__kpi">
                <p class="orders-desk__kpi-label">Pending</p>
                <p class="orders-desk__kpi-value">{{ $pipeline['pending'] }}</p>
            </article>
            <article class="orders-desk__kpi">
                <p class="orders-desk__kpi-label">Processing</p>
                <p class="orders-desk__kpi-value">{{ $pipeline['processing'] }}</p>
            </article>
            <article class="orders-desk__kpi">
                <p class="orders-desk__kpi-label">Shipped</p>
                <p class="orders-desk__kpi-value">{{ $pipeline['shipped'] }}</p>
            </article>
            <article class="orders-desk__kpi orders-desk__kpi--accent">
                <p class="orders-desk__kpi-label">Delivered</p>
                <p class="orders-desk__kpi-value">{{ $pipeline['delivered'] }}</p>
                <p class="orders-desk__kpi-hint">
                    {{ $pipeline['cancelled'] }} cancelled · {{ $pipeline['refunded'] ?? 0 }} refunded
                </p>
            </article>
        </section>

        <div class="orders-desk__pipeline" role="tablist" aria-label="Order status pipeline">
            @foreach ($statusChips as $key => $label)
                @php
                    $href = $key === 'all'
                        ? route('admin.orders.index', array_filter(['search' => $search, 'refund' => $refund]))
                        : route('admin.orders.index', array_filter(['status' => $key, 'search' => $search, 'refund' => $refund]));
                    $active = ($key === 'all' && ! $status) || (string) $status === (string) $key;
                    $count = $key === 'all' ? ($pipeline['all'] ?? 0) : ($pipeline[$key] ?? 0);
                @endphp
                <a href="{{ $href }}"
                    class="orders-desk__chip{{ $active ? ' is-active' : '' }}{{ $key === 'refunded' ? ' is-refund' : '' }}"
                    role="tab"
                    aria-selected="{{ $active ? 'true' : 'false' }}">
                    {{ $label }} <em>{{ $count }}</em>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="orders-desk__filters">
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <label class="orders-desk__search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ $search }}"
                    placeholder="Search order #, client, phone, email…">
            </label>
            <select name="refund" class="orders-desk__select">
                <option value="">All refunds</option>
                <option value="any" @selected($refund === 'any')>Has return</option>
                <option value="partial" @selected($refund === 'partial')>Partially refunded</option>
                <option value="full" @selected($refund === 'full')>Fully refunded</option>
            </select>
            <button type="submit" class="orders-desk__primary-btn orders-desk__filter-submit">
                <i class="fas fa-filter" aria-hidden="true"></i>
                Filter
            </button>
            <a href="{{ route('admin.orders.index') }}" class="orders-desk__ghost-btn">Reset</a>
        </form>

        @if ($orders->isNotEmpty())
            <div class="orders-desk__bulk" x-show="true">
                <label class="orders-desk__check">
                    <input type="checkbox"
                        x-ref="selectAll"
                        :checked="allSelected"
                        @change="toggleAll()"
                        aria-label="Select all orders on this page">
                    <span>Select page</span>
                </label>
                <div class="orders-desk__bulk-actions" x-show="count > 0" x-cloak>
                    <p class="orders-desk__bulk-count">
                        <strong x-text="count">0</strong>
                        <span x-text="count === 1 ? 'order selected' : 'orders selected'"></span>
                    </p>
                    <button type="button" class="orders-desk__ghost-btn" @click="clearSelection()">Clear</button>
                    <button type="button" class="orders-desk__danger-btn" @click="confirmBulkDelete()">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        Delete selected
                    </button>
                </div>
            </div>

            <form x-ref="bulkForm" method="POST" action="{{ route('admin.orders.bulk-destroy') }}" class="hidden">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
            </form>
        @endif

        <div class="orders-desk__tip" role="note">
            <span class="orders-desk__tip-mark" aria-hidden="true">Inspect</span>
            <div>
                <p class="orders-desk__tip-title">Select · inspect · fulfill</p>
                <p class="orders-desk__tip-text">
                    Tick orders to delete in bulk. Use <strong>View products</strong> for line items,
                    then open full details for fulfillment or returns. Refunded orders glow red.
                </p>
            </div>
        </div>

        @if ($orders->isEmpty())
            <div class="orders-desk__empty">
                <p class="orders-desk__empty-eyebrow">Pipeline</p>
                <h2 class="orders-desk__empty-title">No orders yet</h2>
                <p class="orders-desk__empty-text">
                    Website checkouts land here — or create a phone order now.
                </p>
                <a href="{{ route('admin.orders.create') }}" class="orders-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Create order
                </a>
            </div>
        @else
            <div class="orders-desk__list" role="list">
                @foreach ($orders as $index => $order)
                    @php
                        $clientName = $order->customerName();
                        $clientPhone = $order->customerPhone();
                        $clientEmail = $order->customerEmail();
                        $placedAt = $order->placed_at ?? $order->created_at;
                        $refunded = (float) ($order->refunded_total ?? 0);
                        $isFullyRefunded = $refunded > 0 && $refunded >= (float) $order->total_amount - 0.001;
                        $isPartiallyRefunded = $refunded > 0 && ! $isFullyRefunded;
                        $isRefundState = $order->status === 'refunded' || $isFullyRefunded || $isPartiallyRefunded;
                        $itemsCount = (int) ($order->items_count ?? $order->items->count());
                        $pieces = (int) $order->items->sum('qty');
                        $statusVariant = match ($order->status) {
                            'delivered' => 'success',
                            'cancelled', 'refunded' => 'danger',
                            'shipped' => 'info',
                            default => 'warning',
                        };
                    @endphp
                    <article
                        class="orders-desk__row{{ $isRefundState ? ' is-refunded' : '' }}{{ $isFullyRefunded || $order->status === 'refunded' ? ' is-refunded-full' : '' }}"
                        style="--od-i: {{ $index }};"
                        role="listitem"
                        :class="{
                            'is-open': isOpen('{{ $order->id }}'),
                            'is-selected': isChecked('{{ $order->id }}'),
                        }">

                        <div class="orders-desk__row-main">
                            <label class="orders-desk__row-check">
                                <input type="checkbox"
                                    :checked="isChecked('{{ $order->id }}')"
                                    @change="toggleSelect('{{ $order->id }}')"
                                    aria-label="Select order {{ $order->order_number }}">
                            </label>

                            <div class="orders-desk__identity">
                                <div class="orders-desk__id-block">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="orders-desk__number">
                                        {{ $order->order_number }}
                                    </a>
                                    <div class="orders-desk__badges">
                                        <x-admin.badge :variant="$statusVariant">
                                            {{ ucfirst($order->status) }}
                                        </x-admin.badge>
                                        @if ($isFullyRefunded)
                                            <span class="orders-desk__refund-pill is-full">Fully refunded</span>
                                        @elseif ($isPartiallyRefunded)
                                            <span class="orders-desk__refund-pill is-partial">Partial refund</span>
                                        @elseif ($order->status === 'refunded')
                                            <span class="orders-desk__refund-pill is-full">Refunded</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="orders-desk__client">
                                    <p class="orders-desk__client-name">{{ $clientName }}</p>
                                    <p class="orders-desk__client-meta">
                                        @if ($clientPhone)
                                            <span class="tabular-nums">{{ $clientPhone }}</span>
                                        @endif
                                        @if ($clientPhone && $clientEmail)
                                            <span aria-hidden="true">·</span>
                                        @endif
                                        @if ($clientEmail)
                                            <span class="orders-desk__email">{{ $clientEmail }}</span>
                                        @endif
                                        @if ($order->shippingAddress?->city)
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $order->shippingAddress->city }}</span>
                                        @endif
                                    </p>
                                </div>

                                <dl class="orders-desk__facts">
                                    <div>
                                        <dt>Placed</dt>
                                        <dd>
                                            {{ $placedAt->format('M d, Y') }}
                                            <small>{{ $placedAt->format('h:i A') }} · {{ $placedAt->diffForHumans() }}</small>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Total</dt>
                                        <dd class="orders-desk__money">
                                            {{ \App\Support\Money::format($order->total_amount) }}
                                            @if ($refunded > 0)
                                                <small class="is-refund">−{{ \App\Support\Money::format($refunded) }} refunded</small>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Lines</dt>
                                        <dd>
                                            {{ $itemsCount }} {{ Str::plural('item', $itemsCount) }}
                                            <small>{{ $pieces }} {{ Str::plural('piece', $pieces) }}</small>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="orders-desk__actions">
                                <button type="button"
                                    class="orders-desk__action is-accent"
                                    @click="toggle('{{ $order->id }}')"
                                    :aria-expanded="isOpen('{{ $order->id }}') ? 'true' : 'false'"
                                    aria-controls="order-products-{{ $order->id }}">
                                    <i class="fas fa-box-open" aria-hidden="true"></i>
                                    <span x-text="isOpen('{{ $order->id }}') ? 'Hide products' : 'View products'"></span>
                                </button>
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="orders-desk__action is-primary">
                                    Details
                                </a>
                                <a href="{{ route('admin.orders.edit', $order->id) }}" class="orders-desk__action" title="Edit">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </a>
                                <form action="{{ route('admin.orders.destroy', $order->id) }}" method="POST"
                                    data-confirm="Delete this order permanently?" data-confirm-title="Delete order?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="orders-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div id="order-products-{{ $order->id }}"
                            class="orders-desk__drawer"
                            x-show="isOpen('{{ $order->id }}')"
                            x-collapse
                            x-cloak>

                            <div class="orders-desk__drawer-head">
                                <div>
                                    <p class="orders-desk__drawer-eyebrow">Order products</p>
                                    <h3 class="orders-desk__drawer-title">
                                        {{ $order->order_number }}
                                        <em>{{ $pieces }} {{ Str::plural('piece', $pieces) }}</em>
                                    </h3>
                                </div>
                                <div class="orders-desk__drawer-links">
                                    <a href="{{ route('admin.orders.show', $order->id) }}">Open full order</a>
                                    @if ((float) $order->total_amount - $refunded > 0.001)
                                        <a href="{{ route('admin.orders.returns.create', $order->id) }}">Issue return</a>
                                    @endif
                                </div>
                            </div>

                            @if ($order->items->isEmpty())
                                <div class="orders-desk__drawer-empty">
                                    <p>No line items on this order.</p>
                                </div>
                            @else
                                <div class="orders-desk__products">
                                    @foreach ($order->items as $item)
                                        @php
                                            $photo = $item->variant?->photos?->first()
                                                ?? $item->product?->photos?->first();
                                            $color = $item->variant?->color?->name;
                                            $editHref = $item->product_id
                                                ? route('admin.products.edit', $item->product_id)
                                                : null;
                                        @endphp
                                        <div class="orders-desk__product">
                                            <span class="orders-desk__product-media">
                                                @if ($photo)
                                                    <img src="{{ $photo->url }}" alt="{{ $item->name }}" loading="lazy">
                                                @else
                                                    <span class="orders-desk__product-fallback">
                                                        {{ mb_strtoupper(mb_substr((string) $item->name, 0, 1)) }}
                                                    </span>
                                                @endif
                                                <span class="orders-desk__product-qty">×{{ $item->qty }}</span>
                                            </span>
                                            <span class="orders-desk__product-body">
                                                @if ($editHref)
                                                    <a href="{{ $editHref }}" class="orders-desk__product-name">{{ $item->name }}</a>
                                                @else
                                                    <span class="orders-desk__product-name">{{ $item->name }}</span>
                                                @endif
                                                <span class="orders-desk__product-meta">
                                                    @if ($color)
                                                        <span>{{ $color }}</span>
                                                        <span aria-hidden="true">·</span>
                                                    @endif
                                                    @if ($item->sku)
                                                        <code>{{ $item->sku }}</code>
                                                        <span aria-hidden="true">·</span>
                                                    @endif
                                                    <span>{{ \App\Support\Money::format($item->unit_price) }} each</span>
                                                </span>
                                                <span class="orders-desk__product-line">
                                                    Line {{ \App\Support\Money::format($item->line_total) }}
                                                </span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($orders->hasPages())
                <div class="orders-desk__pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
