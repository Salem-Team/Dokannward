@extends('admin.layouts.app')

@section('title', 'Customers')

@section('content')
    @php
        $search = request('search');
        $initialOpen = collect([$openCustomerId ?? null])->filter()->values()->all();
        $segments = [
            'all' => ['All', $stats['total']],
            'buyers' => ['Buyers', $stats['buyers']],
            'accounts' => ['Accounts', $stats['accounts']],
            'guests' => ['Guests', $stats['guests']],
            'dormant' => ['No orders', $stats['dormant']],
            'inactive' => ['Inactive', $stats['inactive']],
        ];
    @endphp

    <div class="brand-studio-page customer-desk"
        x-data="{
            open: @js($initialOpen),
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
        }">

        <header class="customer-desk__hero">
            <div class="customer-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Commerce · CRM</p>
                <h1 class="brand-studio-page__title">Customers</h1>
                <p class="customer-desk__lede">
                    The people behind every order — accounts, guest checkouts, and buying history.
                    Expand a row to review recent orders without leaving this desk.
                </p>
            </div>
            <div class="customer-desk__hero-actions">
                <a href="{{ route('admin.orders.index') }}" class="customer-desk__ghost-btn">
                    Orders desk <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('admin.customers.create') }}" class="customer-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add customer
                </a>
            </div>
        </header>
<section class="customer-desk__kpis" aria-label="Customer summary">
            <article class="customer-desk__kpi">
                <p class="customer-desk__kpi-label">Customers</p>
                <p class="customer-desk__kpi-value">{{ $stats['total'] }}</p>
            </article>
            <article class="customer-desk__kpi customer-desk__kpi--live">
                <p class="customer-desk__kpi-label">Buyers</p>
                <p class="customer-desk__kpi-value">{{ $stats['buyers'] }}</p>
                <p class="customer-desk__kpi-hint">Placed ≥ 1 order</p>
            </article>
            <article class="customer-desk__kpi">
                <p class="customer-desk__kpi-label">Accounts</p>
                <p class="customer-desk__kpi-value">{{ $stats['accounts'] }}</p>
            </article>
            <article class="customer-desk__kpi">
                <p class="customer-desk__kpi-label">Guests</p>
                <p class="customer-desk__kpi-value">{{ $stats['guests'] }}</p>
            </article>
            <article class="customer-desk__kpi customer-desk__kpi--accent">
                <p class="customer-desk__kpi-label">New this month</p>
                <p class="customer-desk__kpi-value">{{ $stats['new_month'] }}</p>
                <p class="customer-desk__kpi-hint">{{ $stats['dormant'] }} with no orders</p>
            </article>
        </section>

        <div class="customer-desk__tip" role="note">
            <span class="customer-desk__tip-mark" aria-hidden="true">Insight</span>
            <div>
                <p class="customer-desk__tip-title">Customers vs guest checkouts</p>
                <p class="customer-desk__tip-text">
                    Registered accounts can log in and reorder. Guest rows are checkout identities —
                    still useful for support, WhatsApp, and order history.
                </p>
            </div>
        </div>

        <div class="customer-desk__toolbar">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="customer-desk__search-form">
                @if ($segment !== 'all')
                    <input type="hidden" name="segment" value="{{ $segment }}">
                @endif
                <label class="customer-desk__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search name, email, phone, city…"
                        autocomplete="off">
                </label>
            </form>

            <div class="customer-desk__segments" role="tablist" aria-label="Customer segments">
                @foreach ($segments as $key => [$label, $count])
                    <a href="{{ route('admin.customers.index', array_filter(['segment' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}"
                        class="customer-desk__segment{{ $segment === $key ? ' is-active' : '' }}"
                        role="tab"
                        aria-selected="{{ $segment === $key ? 'true' : 'false' }}">
                        {{ $label }} <em>{{ $count }}</em>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($customers->isEmpty())
            <div class="customer-desk__empty">
                <p class="customer-desk__empty-eyebrow">CRM</p>
                <h2 class="customer-desk__empty-title">
                    {{ $search || $segment !== 'all' ? 'No customers match' : 'No customers yet' }}
                </h2>
                <p class="customer-desk__empty-text">
                    @if ($search || $segment !== 'all')
                        Try another search or segment — or clear filters to see the full list.
                    @else
                        Customers appear when someone creates an account or checks out as a guest.
                        You can also add one manually for support.
                    @endif
                </p>
                @if ($search || $segment !== 'all')
                    <a href="{{ route('admin.customers.index') }}" class="customer-desk__ghost-btn">Clear filters</a>
                @else
                    <a href="{{ route('admin.customers.create') }}" class="customer-desk__primary-btn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Add customer
                    </a>
                @endif
            </div>
        @else
            <div class="customer-desk__list" role="list">
                @foreach ($customers as $index => $customer)
                    @php
                        $displayName = trim((string) ($customer->name ?: trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')))) ?: 'Customer';
                        $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                        $ordersCount = (int) $customer->orders_count;
                        $lifetime = (float) ($customer->orders_total ?? 0);
                        $kind = $customer->is_guest ? 'guest' : 'account';
                        $isActive = $customer->is_active !== false;
                        $latestAddress = $customer->addresses->first();
                        $waPhone = $customer->phone ? preg_replace('/\D+/', '', $customer->phone) : null;
                        if ($waPhone && str_starts_with($waPhone, '0')) {
                            $waPhone = '20'.substr($waPhone, 1);
                        }
                    @endphp
                    <article
                        class="customer-desk__row"
                        style="--cd-i: {{ $index }};"
                        role="listitem"
                        :class="isOpen('{{ $customer->id }}') && 'is-open'">

                        <div class="customer-desk__row-main">
                            <div class="customer-desk__identity">
                                <button type="button"
                                    class="customer-desk__avatar"
                                    @click="toggle('{{ $customer->id }}')"
                                    :aria-expanded="isOpen('{{ $customer->id }}')"
                                    aria-label="Toggle orders for {{ $displayName }}">
                                    <span>{{ $initial }}</span>
                                </button>
                                <div class="customer-desk__who min-w-0">
                                    <div class="customer-desk__name-row">
                                        <h2 class="customer-desk__name">
                                            <a href="{{ route('admin.customers.edit', $customer->id) }}">{{ $displayName }}</a>
                                        </h2>
                                        <span class="customer-desk__kind customer-desk__kind--{{ $kind }}">
                                            {{ $kind === 'guest' ? 'Guest' : 'Account' }}
                                        </span>
                                        @unless ($isActive)
                                            <span class="customer-desk__kind customer-desk__kind--off">Inactive</span>
                                        @endunless
                                    </div>
                                    <p class="customer-desk__meta">
                                        <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                                        @if ($customer->phone)
                                            <span aria-hidden="true">·</span>
                                            <a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>
                                        @endif
                                        @if ($latestAddress?->city)
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $latestAddress->city }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <dl class="customer-desk__stats">
                                <div>
                                    <dt>Orders</dt>
                                    <dd>{{ $ordersCount }}</dd>
                                </div>
                                <div>
                                    <dt>Lifetime</dt>
                                    <dd>{{ $lifetime > 0 ? \App\Support\Money::format($lifetime) : '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Joined</dt>
                                    <dd>{{ $customer->created_at?->format('M d, Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Last login</dt>
                                    <dd>{{ $customer->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                                </div>
                            </dl>

                            <div class="customer-desk__actions">
                                <button type="button"
                                    class="customer-desk__action"
                                    @click="toggle('{{ $customer->id }}')"
                                    :aria-expanded="isOpen('{{ $customer->id }}')">
                                    <i class="fas fa-receipt" aria-hidden="true"></i>
                                    <span x-text="isOpen('{{ $customer->id }}') ? 'Hide details' : 'View details'"></span>
                                </button>
                                @if ($customer->email)
                                    <a href="mailto:{{ $customer->email }}" class="customer-desk__action" title="Email">
                                        <i class="fas fa-envelope" aria-hidden="true"></i>
                                    </a>
                                @endif
                                @if ($waPhone)
                                    <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                                        class="customer-desk__action" title="WhatsApp">
                                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                    </a>
                                @endif
                                <a href="{{ route('admin.customers.edit', $customer->id) }}"
                                    class="customer-desk__action is-primary">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    Edit
                                </a>
                                <form action="{{ route('admin.customers.destroy', $customer->id) }}"
                                    method="POST"
                                    class="customer-desk__delete"
                                    data-confirm="Delete this customer? Orders stay in history if linked by email." data-confirm-title="Delete customer?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="customer-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="customer-desk__orders"
                            x-show="isOpen('{{ $customer->id }}')"
                            x-collapse
                            x-cloak>
                            <div class="customer-desk__orders-inner">
                                <div class="customer-desk__addresses">
                                    <div class="customer-desk__orders-head">
                                        <p class="customer-desk__orders-title">Addresses</p>
                                        <span class="customer-desk__address-count">
                                            {{ $customer->addresses->count() }}
                                            {{ \Illuminate\Support\Str::plural('pin', $customer->addresses->count()) }}
                                        </span>
                                    </div>

                                    @if ($customer->addresses->isEmpty())
                                        <p class="customer-desk__orders-empty">No delivery address on file yet.</p>
                                    @else
                                        <ul class="customer-desk__address-list">
                                            @foreach ($customer->addresses as $address)
                                                <li class="customer-desk__address">
                                                    @if ($address->hasCoordinates())
                                                        <div class="customer-desk__address-map">
                                                            <img src="{{ $address->osmTileUrl() }}" alt="" width="220" height="140">
                                                            <span class="customer-desk__address-dot" aria-hidden="true"></span>
                                                        </div>
                                                    @endif
                                                    <div class="customer-desk__address-copy">
                                                        <p class="customer-desk__address-label">{{ $address->label ?: 'Shipping' }}</p>
                                                        <p class="customer-desk__address-name">{{ $address->recipient_name }}</p>
                                                        <p>{{ $address->line_1 }}</p>
                                                        @if ($address->line_2)
                                                            <p>{{ $address->line_2 }}</p>
                                                        @endif
                                                        <p>
                                                            {{ $address->city }}
                                                            @if ($address->postal_code)
                                                                <span>{{ $address->postal_code }}</span>
                                                            @endif
                                                        </p>
                                                        @if ($address->place_name)
                                                            <p class="customer-desk__address-place">{{ $address->place_name }}</p>
                                                        @endif
                                                        @if ($address->hasCoordinates())
                                                            <p class="customer-desk__address-coords">
                                                                {{ number_format((float) $address->latitude, 5) }},
                                                                {{ number_format((float) $address->longitude, 5) }}
                                                            </p>
                                                            <div class="customer-desk__address-links">
                                                                <a href="{{ $address->googleMapsUrl() }}" target="_blank" rel="noopener">Google Maps</a>
                                                                <a href="{{ $address->appleMapsUrl() }}" target="_blank" rel="noopener">Apple Maps</a>
                                                                <a href="{{ $address->osmUrl() }}" target="_blank" rel="noopener">OpenStreetMap</a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="customer-desk__orders-head">
                                    <p class="customer-desk__orders-title">Recent orders</p>
                                    @if ($ordersCount > 0)
                                        <a href="{{ route('admin.orders.index', ['search' => $customer->email]) }}"
                                            class="customer-desk__orders-link">
                                            Open in orders desk <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </div>

                                @if ($customer->orders->isEmpty())
                                    <p class="customer-desk__orders-empty">No orders linked to this customer yet.</p>
                                @else
                                    <ul class="customer-desk__order-list">
                                        @foreach ($customer->orders as $order)
                                            <li>
                                                <a href="{{ route('admin.orders.show', $order->id) }}"
                                                    class="customer-desk__order">
                                                    <span class="customer-desk__order-num">{{ $order->order_number }}</span>
                                                    <span class="customer-desk__order-status" data-status="{{ $order->status }}">
                                                        {{ ucfirst($order->status) }}
                                                    </span>
                                                    <span class="customer-desk__order-meta">
                                                        {{ ($order->placed_at ?? $order->created_at)?->format('M d, Y') }}
                                                        · {{ (int) $order->items_count }}
                                                        {{ \Illuminate\Support\Str::plural('item', (int) $order->items_count) }}
                                                    </span>
                                                    <span class="customer-desk__order-total">
                                                        {{ \App\Support\Money::format($order->total_amount) }}
                                                    </span>
                                                    <i class="fas fa-chevron-right customer-desk__order-chevron" aria-hidden="true"></i>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($ordersCount > $customer->orders->count())
                                        <p class="customer-desk__orders-more">
                                            Showing {{ $customer->orders->count() }} of {{ $ordersCount }} orders
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($customers->hasPages())
                <div class="customer-desk__pagination">
                    {{ $customers->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
