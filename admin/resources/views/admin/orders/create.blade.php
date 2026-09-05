@extends('admin.layouts.app')

@section('title', 'Create Order')

@php
    $oldItems = collect(old('items', []))->values()->all();
@endphp

@push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .order-desk__section {
            background: var(--color-white, #fff);
            border: 1px solid var(--color-line, #e5e5e5);
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
        }
        .dark .order-desk__section {
            background: #1f2937;
            border-color: #374151;
        }
        .order-desk__eyebrow {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--color-ash, #6b6b6b);
        }
        .order-desk__title {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-top: 0.25rem;
            color: var(--color-ink, #0a0a0a);
        }
        .dark .order-desk__title { color: #f3f4f6; }
        .order-desk__hint {
            font-size: 0.8125rem;
            color: var(--color-ash, #6b6b6b);
            margin-top: 0.35rem;
            line-height: 1.5;
        }
        .order-desk__label {
            display: block;
            font-size: 10px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--color-ash, #6b6b6b);
            margin-bottom: 0.5rem;
        }
        .order-desk__input {
            width: 100%;
            border: 1px solid var(--color-line, #e5e5e5);
            background: var(--color-paper, #f7f7f5);
            color: var(--color-ink, #0a0a0a);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
        }
        .dark .order-desk__input {
            background: #111827;
            border-color: #374151;
            color: #f3f4f6;
        }
        .order-desk__input:focus {
            outline: none;
            border-color: var(--color-ink, #0a0a0a);
            box-shadow: 0 0 0 3px rgb(10 10 10 / 0.08);
        }
        .order-desk__hit {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
            text-align: left;
            padding: 0.85rem 1rem;
            border: 1px solid var(--color-line, #e5e5e5);
            border-radius: 8px;
            background: var(--color-paper, #f7f7f5);
            transition: border-color .15s ease, background .15s ease;
        }
        .order-desk__hit:hover {
            border-color: var(--color-ink, #0a0a0a);
            background: #fff;
        }
        .dark .order-desk__hit {
            background: #111827;
            border-color: #374151;
        }
        .order-desk__line {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--color-line, #e5e5e5);
        }
        @media (max-width: 720px) {
            .order-desk__line {
                grid-template-columns: 1fr auto;
            }
        }
        .order-desk__totals {
            position: sticky;
            top: 1.25rem;
        }
        .order-desk__totals-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.9rem;
            padding: 0.35rem 0;
            color: var(--color-graphite, #3f3f3f);
        }
        .order-desk__totals-row.is-total {
            margin-top: 0.75rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--color-line, #e5e5e5);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--color-ink, #0a0a0a);
        }
        .dark .order-desk__totals-row.is-total { color: #f3f4f6; }
        .order-desk__step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 999px;
            background: var(--color-ink, #0a0a0a);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            margin-right: 0.55rem;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            function registerOrderCreate() {
                if (!window.Alpine || Alpine.data._dokannwardOrderCreate) return;
                Alpine.data._dokannwardOrderCreate = true;
                Alpine.data('orderCreateDesk', (config) => ({
                    searchUrl: config.searchUrl,
                    quote: config.quote,
                    q: '',
                    searching: false,
                    hits: [],
                    lines: config.oldItems || [],
                    timer: null,
                    get subtotal() {
                        return this.lines.reduce((sum, line) => sum + (Number(line.unit_price) * Number(line.qty || 0)), 0);
                    },
                    get shipping() {
                        return Math.max(0, Number(this.quote.standard_shipping_fee || 0));
                    },
                    get tax() {
                        if (!this.quote.tax_enabled) return 0;
                        return Math.round(this.subtotal * (Number(this.quote.tax_rate || 0) / 100) * 100) / 100;
                    },
                    get total() {
                        return Math.round((this.subtotal + this.shipping + this.tax) * 100) / 100;
                    },
                    money(n) {
                        const amount = Number(n || 0).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                        const symbol = this.quote.currency_symbol || 'LE';
                        return (this.quote.currency_position || 'before') === 'after'
                            ? `${amount} ${symbol}`
                            : `${symbol} ${amount}`;
                    },
                    scheduleSearch() {
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => this.search(), 280);
                    },
                    async search() {
                        const q = this.q.trim();
                        if (q.length < 1) {
                            this.hits = [];
                            return;
                        }
                        this.searching = true;
                        try {
                            const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(q)}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            });
                            const json = await res.json();
                            this.hits = Array.isArray(json.data) ? json.data : [];
                        } catch (_) {
                            this.hits = [];
                        } finally {
                            this.searching = false;
                        }
                    },
                    addProduct(product) {
                        if (product.variants && product.variants.length > 0) {
                            const available = product.variants.find((v) => v.stock > 0) || product.variants[0];
                            this.addLine({
                                product_id: product.id,
                                variant_id: available.id,
                                name: `${product.name} · ${available.label}`,
                                sku: available.sku || product.sku,
                                unit_price: available.price,
                                stock: available.stock,
                                qty: 1,
                                variants: product.variants,
                            });
                        } else {
                            if (!product.in_stock) return;
                            this.addLine({
                                product_id: product.id,
                                variant_id: '',
                                name: product.name,
                                sku: product.sku,
                                unit_price: product.price,
                                stock: product.in_stock ? 99 : 0,
                                qty: 1,
                                variants: [],
                            });
                        }
                        this.q = '';
                        this.hits = [];
                    },
                    addLine(line) {
                        const key = `${line.product_id}:${line.variant_id || ''}`;
                        const existing = this.lines.find((l) => `${l.product_id}:${l.variant_id || ''}` === key);
                        if (existing) {
                            existing.qty = Math.min(99, Number(existing.qty || 0) + 1);
                            return;
                        }
                        this.lines.push(line);
                    },
                    changeVariant(line, variantId) {
                        const variant = (line.variants || []).find((v) => v.id === variantId);
                        if (!variant) return;
                        line.variant_id = variant.id;
                        line.unit_price = variant.price;
                        line.stock = variant.stock;
                        line.sku = variant.sku || line.sku;
                        line.name = line.name.split(' · ')[0] + ' · ' + variant.label;
                        if (Number(line.qty) > Number(variant.stock)) {
                            line.qty = Math.max(1, variant.stock);
                        }
                    },
                    removeLine(index) {
                        this.lines.splice(index, 1);
                    },
                }));
            }

            if (window.Alpine) registerOrderCreate();
            else document.addEventListener('alpine:init', registerOrderCreate);
        })();
    </script>
@endpush

@section('content')
    <div class="brand-studio-page"
        x-data="orderCreateDesk(@js([
            'searchUrl' => $searchUrl,
            'quote' => $quote,
            'oldItems' => $oldItems,
        ]))"
        x-cloak>

        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Dokan Ward Commerce · Orders</p>
                <h1 class="brand-studio-page__title">Create order</h1>
                <p class="brand-studio-page__sub">
                    Manual / phone orders use the same pricing & stock rules as website checkout.
                </p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.orders.index') }}'">
                Back to Orders
            </x-admin.button>
        </div>

        @if ($errors->any())
            <x-admin.alert type="error" class="mb-6">
                <p class="font-medium mb-1">Could not place this order</p>
                <ul class="list-disc pl-5 text-sm space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-admin.alert>
        @endif

        <form method="POST" action="{{ route('admin.orders.store') }}" class="mt-2"
            @submit="if (lines.length === 0) { $event.preventDefault(); window.dialog?.alert?.({ title: 'Missing products', message: 'Add at least one product before creating the order.', tone: 'warning', eyebrow: 'Validation' }); }">
            @csrf

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-6">
                    <section class="order-desk__section">
                        <p class="order-desk__eyebrow"><span class="order-desk__step">1</span> Client</p>
                        <h2 class="order-desk__title">Customer & shipping</h2>
                        <p class="order-desk__hint">Who is receiving the order — same fields as storefront checkout.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                            <div>
                                <label class="order-desk__label" for="recipient_name">Full name</label>
                                <input id="recipient_name" name="recipient_name" type="text" required
                                    value="{{ old('recipient_name') }}" class="order-desk__input"
                                    placeholder="Client name">
                            </div>
                            <div>
                                <label class="order-desk__label" for="phone">Phone</label>
                                <input id="phone" name="phone" type="text" required
                                    value="{{ old('phone') }}" class="order-desk__input"
                                    placeholder="01xxxxxxxxx">
                            </div>
                            <div class="md:col-span-2">
                                <label class="order-desk__label" for="email">Email <span class="normal-case tracking-normal font-normal">(optional)</span></label>
                                <input id="email" name="email" type="email"
                                    value="{{ old('email') }}" class="order-desk__input"
                                    placeholder="client@email.com">
                            </div>
                            <div class="md:col-span-2">
                                <label class="order-desk__label" for="line_1">Address line 1</label>
                                <input id="line_1" name="line_1" type="text" required
                                    value="{{ old('line_1') }}" class="order-desk__input"
                                    placeholder="Street, building, floor">
                            </div>
                            <div class="md:col-span-2">
                                <label class="order-desk__label" for="line_2">Address line 2</label>
                                <input id="line_2" name="line_2" type="text"
                                    value="{{ old('line_2') }}" class="order-desk__input"
                                    placeholder="Landmark (optional)">
                            </div>
                            <div>
                                <label class="order-desk__label" for="city">City</label>
                                <input id="city" name="city" type="text" required
                                    value="{{ old('city') }}" class="order-desk__input"
                                    placeholder="Cairo">
                            </div>
                            <div>
                                <label class="order-desk__label" for="postal_code">Postal code</label>
                                <input id="postal_code" name="postal_code" type="text"
                                    value="{{ old('postal_code') }}" class="order-desk__input">
                            </div>
                            <div>
                                <label class="order-desk__label" for="country">Country</label>
                                <input id="country" name="country" type="text"
                                    value="{{ old('country', 'Egypt') }}" class="order-desk__input">
                            </div>
                            <div>
                                <label class="order-desk__label" for="status">Initial status</label>
                                <select id="status" name="status" class="order-desk__input">
                                    <option value="pending" @selected(old('status', 'pending') === 'pending')>Pending</option>
                                    <option value="processing" @selected(old('status') === 'processing')>Processing (confirmed)</option>
                                </select>
                            </div>
                            <div>
                                <label class="order-desk__label" for="payment_method">Payment method</label>
                                <select id="payment_method" name="payment_method" class="order-desk__input">
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method['key'] }}"
                                            @selected(old('payment_method', $defaultPaymentMethod) === $method['key'])>
                                            {{ $method['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="order-desk__label" for="notes">Internal notes</label>
                                <textarea id="notes" name="notes" rows="3" class="order-desk__input"
                                    placeholder="WhatsApp order, gift wrap, delivery window…">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="order-desk__section">
                        <p class="order-desk__eyebrow"><span class="order-desk__step">2</span> Catalog</p>
                        <h2 class="order-desk__title">Line items</h2>
                        <p class="order-desk__hint">Search by name or SKU. Prices and stock come from the live catalog.</p>

                        <div class="mt-5 relative">
                            <label class="order-desk__label" for="catalog-q">Add product</label>
                            <input id="catalog-q" type="search" x-model="q" @input="scheduleSearch()"
                                class="order-desk__input" placeholder="Type product name or SKU…"
                                autocomplete="off">
                            <p class="order-desk__hint mt-2" x-show="searching">Searching catalog…</p>

                            <div class="mt-3 space-y-2" x-show="hits.length > 0">
                                <template x-for="product in hits" :key="product.id">
                                    <button type="button" class="order-desk__hit" @click="addProduct(product)">
                                        <span>
                                            <span class="block font-medium text-zibra-ink dark:text-white" x-text="product.name"></span>
                                            <span class="block text-xs text-zibra-ash mt-0.5 font-mono" x-text="product.sku"></span>
                                        </span>
                                        <span class="text-sm font-semibold tabular-nums" x-text="money(product.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="mt-6">
                            <template x-if="lines.length === 0">
                                <div class="py-10 text-center border border-dashed border-zibra-line dark:border-gray-600 rounded-xl">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-zibra-ash mb-2">No lines yet</p>
                                    <p class="text-sm text-zibra-ash">Search above to add the first product.</p>
                                </div>
                            </template>

                            <template x-for="(line, index) in lines" :key="index">
                                <div class="order-desk__line">
                                    <div class="min-w-0">
                                        <p class="font-medium text-zibra-ink dark:text-white truncate" x-text="line.name"></p>
                                        <p class="text-xs text-zibra-ash font-mono mt-0.5" x-text="line.sku"></p>
                                        <template x-if="line.variants && line.variants.length">
                                            <select class="order-desk__input mt-2 max-w-xs"
                                                :value="line.variant_id"
                                                @change="changeVariant(line, $event.target.value)">
                                                <template x-for="v in line.variants" :key="v.id">
                                                    <option :value="v.id"
                                                        :disabled="v.stock <= 0 && v.id !== line.variant_id"
                                                        x-text="`${v.label} · ${money(v.price)} · ${v.stock} left`"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <input type="hidden" :name="`items[${index}][product_id]`" :value="line.product_id">
                                        <input type="hidden" :name="`items[${index}][variant_id]`" :value="line.variant_id || ''">
                                        <input type="hidden" :name="`items[${index}][name]`" :value="line.name">
                                        <input type="hidden" :name="`items[${index}][sku]`" :value="line.sku || ''">
                                    </div>
                                    <div>
                                        <label class="order-desk__label">Qty</label>
                                        <input type="number" min="1" max="99" x-model.number="line.qty"
                                            :name="`items[${index}][qty]`"
                                            class="order-desk__input w-20">
                                    </div>
                                    <div class="text-right">
                                        <p class="order-desk__label">Line</p>
                                        <p class="font-semibold tabular-nums" x-text="money(line.unit_price * line.qty)"></p>
                                    </div>
                                    <div>
                                        <button type="button" class="h-10 w-10 border border-zibra-line text-zibra-ash hover:border-red-500 hover:text-red-600"
                                            @click="removeLine(index)" title="Remove">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>

                <aside class="order-desk__totals">
                    <section class="order-desk__section">
                        <p class="order-desk__eyebrow"><span class="order-desk__step">3</span> Review</p>
                        <h2 class="order-desk__title">Quote</h2>
                        <p class="order-desk__hint">Final totals recalculate on the server from live prices.</p>

                        <div class="mt-5">
                            <div class="order-desk__totals-row">
                                <span>Subtotal</span>
                                <span class="tabular-nums" x-text="money(subtotal)"></span>
                            </div>
                            <div class="order-desk__totals-row">
                                <span>Shipping</span>
                                <span class="tabular-nums" x-text="shipping === 0 ? 'Free' : money(shipping)"></span>
                            </div>
                            <div class="order-desk__totals-row" x-show="quote.tax_enabled">
                                <span>Tax</span>
                                <span class="tabular-nums" x-text="money(tax)"></span>
                            </div>
                            <div class="order-desk__totals-row is-total">
                                <span>Total</span>
                                <span class="tabular-nums" x-text="money(total)"></span>
                            </div>
                        </div>

                        <p class="order-desk__hint mt-4">
                            <span x-text="lines.length"></span> line<span x-show="lines.length !== 1">s</span>
                            · stock will be reserved on create
                        </p>

                        <div class="mt-6 space-y-2">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-12 px-4 bg-zibra-ink text-white text-xs uppercase tracking-[0.16em] font-semibold hover:bg-black transition-colors">
                                <i class="fas fa-check"></i>
                                Place order
                            </button>
                            <button type="button"
                                onclick="window.location='{{ route('admin.orders.index') }}'"
                                class="w-full inline-flex items-center justify-center h-11 px-4 border border-zibra-line text-xs uppercase tracking-[0.16em] text-zibra-ash hover:border-zibra-ink hover:text-zibra-ink transition-colors">
                                Cancel
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>
@endsection
