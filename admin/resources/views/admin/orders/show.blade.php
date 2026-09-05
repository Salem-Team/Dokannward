@extends('admin.layouts.app')

@section('title', 'Order ' . $order->order_number)

@php
    $clientName = $order->customerName();
    $clientPhone = $order->customerPhone();
    $clientEmail = $order->customerEmail();
    $placedAt = $order->placed_at ?? $order->created_at;
    $address = $order->shippingAddress;
    $statusVariant = match ($order->status) {
        'delivered' => 'success',
        'cancelled', 'refunded' => 'danger',
        'shipped' => 'info',
        default => 'warning',
    };
    $initial = mb_strtoupper(mb_substr($clientName !== '' ? $clientName : '?', 0, 1));
    $waPhone = $clientPhone ? preg_replace('/\D+/', '', $clientPhone) : null;
    if ($waPhone && str_starts_with($waPhone, '0')) {
        $waPhone = '20' . substr($waPhone, 1);
    }
    $refundedTotal = (float) ($refundedTotal ?? $order->returns->sum('refund_amount'));
    $refundableRemaining = (float) ($refundableRemaining ?? max(0, round((float) $order->total_amount - $refundedTotal, 2)));
    $isFullyRefunded = $refundedTotal > 0 && $refundableRemaining <= 0.001;
    $isPartiallyRefunded = $refundedTotal > 0 && ! $isFullyRefunded;
    $itemQty = (int) $order->items->sum('qty');
    $netTotal = max(0, (float) $order->total_amount - $refundedTotal);
    $reasonLabels = [
        'changed_mind' => 'Changed mind',
        'damaged' => 'Damaged / defective',
        'wrong_item' => 'Wrong item',
        'size_fit' => 'Size / fit',
        'other' => 'Other',
    ];
    $statusTone = match ($order->status) {
        'delivered' => 'ok',
        'cancelled', 'refunded' => 'bad',
        'shipped', 'processing' => 'info',
        default => 'warn',
    };
@endphp

@push('styles')
    <style>
        .order-page {
            --order-gap: 1.35rem;
            display: flex;
            flex-direction: column;
            gap: var(--order-gap);
        }
        /* Must stay below .admin-topbar (z:40) and notifications drawer (z:120+) */
        .order-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            margin: -0.35rem -0.35rem 0;
            padding: 0.85rem 0.35rem 0.95rem;
            background: color-mix(in srgb, var(--color-paper, #fafafa) 94%, transparent);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid transparent;
        }
        .dark .order-toolbar {
            background: color-mix(in srgb, rgb(17 24 39) 90%, transparent);
        }
        .order-toolbar.is-stuck {
            border-bottom-color: var(--color-line, #e5e5e5);
            box-shadow: 0 10px 28px rgb(0 0 0 / 0.04);
        }
        .dark .order-toolbar.is-stuck {
            border-bottom-color: rgb(55 65 81);
            box-shadow: 0 10px 28px rgb(0 0 0 / 0.25);
        }
        .order-sheet {
            background:
                radial-gradient(90% 70% at 0% 0%, rgb(10 10 10 / 0.03), transparent 50%),
                linear-gradient(180deg, #fafafa 0%, #ffffff 140px);
        }
        .dark .order-sheet {
            background:
                radial-gradient(90% 70% at 0% 0%, rgb(255 255 255 / 0.04), transparent 50%),
                transparent;
        }
        .order-rule {
            height: 1px;
            background: var(--color-line, #e4e4e4);
        }
        .dark .order-rule {
            background: rgb(55 65 81);
        }
        .order-label {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--color-ash, #6b6b6b);
        }
        .order-mono {
            font-variant-numeric: tabular-nums;
            font-family: var(--font-anonymous), ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .order-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            height: 2.35rem;
            padding: 0 0.95rem;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            border: 1px solid var(--color-line, #d6d6d6);
            background: #fff;
            color: var(--color-ink, #0a0a0a);
            transition: border-color .2s cubic-bezier(0.16, 1, 0.3, 1), background .2s ease, color .2s ease, opacity .15s ease, transform .2s ease;
            white-space: nowrap;
        }
        .dark .order-btn {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: #fff;
        }
        .order-btn:hover {
            border-color: var(--color-ink, #0a0a0a);
            transform: translateY(-1px);
        }
        .dark .order-btn:hover {
            border-color: #fff;
        }
        .order-btn--primary {
            background: var(--color-ink, #0a0a0a);
            border-color: var(--color-ink, #0a0a0a);
            color: #fff;
        }
        .order-btn--primary:hover {
            background: #000;
        }
        .order-btn--danger {
            color: #dc2626;
        }
        .order-btn--danger:hover {
            border-color: #ef4444;
            color: #b91c1c;
        }
        .order-btn:disabled {
            opacity: .75;
            cursor: wait;
            transform: none;
        }
        .order-status-select {
            height: 2.35rem;
            border: 1px solid var(--color-line, #d6d6d6);
            background: #fff;
            color: var(--color-ink, #0a0a0a);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0 2rem 0 0.75rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b6b6b' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.65rem center;
        }
        .dark .order-status-select {
            border-color: rgb(75 85 99);
            background-color: rgb(31 41 55);
            color: #fff;
        }
        .order-status-select[data-tone="warn"] { box-shadow: inset 3px 0 0 #d97706; }
        .order-status-select[data-tone="info"] { box-shadow: inset 3px 0 0 #2563eb; }
        .order-status-select[data-tone="ok"] { box-shadow: inset 3px 0 0 #16a34a; }
        .order-status-select[data-tone="bad"] { box-shadow: inset 3px 0 0 #dc2626; }
        .order-contact {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            height: 2rem;
            padding: 0 0.7rem;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            border: 1px solid var(--color-line, #e0e0e0);
            color: #3f3f3f;
            transition: border-color .15s ease, color .15s ease, background .15s ease;
        }
        .dark .order-contact {
            border-color: rgb(75 85 99);
            color: #d1d5db;
        }
        .order-contact:hover {
            border-color: var(--color-ink, #0a0a0a);
            color: var(--color-ink, #0a0a0a);
            background: #f5f5f5;
        }
        .dark .order-contact:hover {
            border-color: #fff;
            color: #fff;
            background: rgb(55 65 81);
        }
        .order-thumb {
            width: 4.25rem;
            height: 4.25rem;
            object-fit: cover;
            background: #f3f3f1;
        }
        .order-pin-map {
            position: relative;
            display: block;
            overflow: hidden;
            height: 9.5rem;
            background: #e8e4dc;
            border: 1px solid var(--color-line, #e4e4e4);
        }
        .order-pin-map img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(0.28) contrast(1.05);
        }
        .order-pin-map__dot {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0.9rem;
            height: 0.9rem;
            border-radius: 999px;
            background: var(--color-ink, #0a0a0a);
            border: 2px solid #fff;
            box-shadow: 0 8px 16px rgb(0 0 0 / 0.28);
            transform: translate(-50%, -50%);
        }
        .order-pin-map__open {
            position: absolute;
            left: 0.7rem;
            bottom: 0.7rem;
            height: 1.7rem;
            padding: 0 0.6rem;
            display: inline-flex;
            align-items: center;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            background: rgb(255 255 255 / 0.94);
            color: var(--color-ink, #0a0a0a);
        }
        .invoice-btn-spinner {
            width: 11px;
            height: 11px;
            border: 1.5px solid rgba(255, 255, 255, 0.28);
            border-top-color: #fff;
            border-radius: 9999px;
            animation: invoice-btn-spin 0.7s linear infinite;
        }
        @keyframes invoice-btn-spin {
            to { transform: rotate(360deg); }
        }
        .order-panel {
            border: 1px solid var(--color-line, #e4e4e4);
            background: #fff;
        }
        .dark .order-panel {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }
        .order-status-card {
            padding: 1.25rem 1.35rem 1.35rem;
            border: 1px solid var(--color-line, #e4e4e4);
            background:
                linear-gradient(155deg, rgb(10 10 10 / 0.035), transparent 55%),
                #fff;
        }
        .dark .order-status-card {
            border-color: rgb(55 65 81);
            background:
                linear-gradient(155deg, rgb(255 255 255 / 0.04), transparent 55%),
                rgb(31 41 55);
        }
        .order-status-card[data-tone="warn"] { box-shadow: inset 3px 0 0 #d97706; }
        .order-status-card[data-tone="info"] { box-shadow: inset 3px 0 0 #2563eb; }
        .order-status-card[data-tone="ok"] { box-shadow: inset 3px 0 0 #16a34a; }
        .order-status-card[data-tone="bad"] { box-shadow: inset 3px 0 0 #dc2626; }
        .order-status-card__value {
            margin: 0.35rem 0 0;
            font-size: clamp(1.65rem, 2.6vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1;
            color: var(--color-ink, #0a0a0a);
        }
        .dark .order-status-card__value { color: #f9fafb; }
        .order-status-card__hint {
            margin: 0.55rem 0 0;
            font-size: 0.8rem;
            color: var(--color-ash, #6b6b6b);
        }
        .order-kpi {
            border: 1px solid var(--color-line, #e4e4e4);
            padding: 1rem 1.05rem;
            background: rgb(255 255 255 / 0.78);
        }
        .dark .order-kpi {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39 / 0.55);
        }
        .order-kpi[data-tone="warn"] { box-shadow: inset 3px 0 0 #d97706; }
        .order-kpi[data-tone="info"] { box-shadow: inset 3px 0 0 #2563eb; }
        .order-kpi[data-tone="ok"] { box-shadow: inset 3px 0 0 #16a34a; }
        .order-kpi[data-tone="bad"] { box-shadow: inset 3px 0 0 #dc2626; }
        .order-side-sticky {
            position: sticky;
            top: 4.75rem;
        }
        [x-cloak] { display: none !important; }
        @media (max-width: 1023px) {
            .order-side-sticky { position: static; }
        }
    </style>
@endpush

@section('content')
    <div class="order-page w-full">
        {{-- Sticky ops toolbar --}}
        <div class="order-toolbar" data-order-toolbar>
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 min-w-0">
                    <a href="{{ route('admin.orders.index') }}"
                        class="inline-flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-zibra-ash hover:text-zibra-ink dark:hover:text-white transition-colors">
                        <i class="fas fa-arrow-left text-[10px]"></i>
                        All orders
                    </a>
                    <span class="hidden sm:inline text-zibra-line dark:text-gray-600">/</span>
                    <div class="flex items-center gap-2 min-w-0" x-data="{ copied: false }">
                        <span class="order-mono text-sm font-semibold text-zibra-ink dark:text-white truncate">{{ $order->order_number }}</span>
                        <button type="button" title="Copy order number"
                            @click="navigator.clipboard.writeText(@js($order->order_number)); copied = true; setTimeout(() => copied = false, 1400)"
                            class="h-7 w-7 inline-flex items-center justify-center border border-zibra-line dark:border-gray-600 text-zibra-ash hover:text-zibra-ink dark:hover:text-white transition-colors">
                            <i class="fas text-[10px]" :class="copied ? 'fa-check text-green-600' : 'fa-copy'"></i>
                        </button>
                        <x-admin.badge :variant="$statusVariant">{{ ucfirst($order->status) }}</x-admin.badge>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.orders.updateStatus', $order->id) }}" class="inline-flex items-center gap-2">
                        @csrf
                        <label for="order-status" class="order-label hidden lg:block">Status</label>
                        <select id="order-status" name="status" onchange="this.form.submit()"
                            data-tone="{{ $statusTone }}"
                            class="order-status-select focus:outline-none focus:border-zibra-ink dark:focus:border-white"
                            title="Update order status">
                            @foreach (\App\Services\OrderLifecycle::STATUSES as $status)
                                <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </form>

                    <a href="{{ route('admin.orders.edit', $order->id) }}" class="order-btn">
                        <i class="fas fa-pen text-[10px]" aria-hidden="true"></i>
                        Edit
                    </a>

                    @if ($refundableRemaining > 0)
                        <a href="{{ route('admin.orders.returns.create', $order->id) }}" class="order-btn">
                            <i class="fas fa-undo text-[10px]" aria-hidden="true"></i>
                            Return
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.orders.destroy', $order->id) }}" class="inline"
                        data-confirm="Delete this order permanently?" data-confirm-title="Delete order?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="order-btn order-btn--danger">
                            <i class="fas fa-trash text-[10px]" aria-hidden="true"></i>
                            Delete
                        </button>
                    </form>

                    <button type="button"
                        data-no-loader
                        data-pdf-url="{{ route('admin.orders.invoice', $order->id, false) }}"
                        data-pdf-filename="invoice-{{ $order->order_number }}.pdf"
                        x-data="{ loading: false }"
                        @click="loading = true; window.dokannwardDownloadPdf($el).finally(() => loading = false)"
                        :disabled="loading"
                        :aria-busy="loading"
                        class="order-btn order-btn--primary relative min-w-[10.75rem]">
                        <span class="inline-flex items-center gap-2" :class="loading && 'invisible'">
                            <i class="fas fa-file-invoice text-[11px]" aria-hidden="true"></i>
                            Download Invoice
                        </span>
                        <span x-show="loading" x-cloak
                            class="absolute inset-0 inline-flex items-center justify-center gap-2.5"
                            aria-live="polite">
                            <span class="invoice-btn-spinner" aria-hidden="true"></span>
                            Preparing…
                        </span>
                    </button>
                </div>
            </div>
        </div>

        @if (session('success'))
            <x-admin.alert type="success">{{ session('success') }}</x-admin.alert>
        @endif

        @if (session('return_credit_note_id'))
            @php
                $autoReturn = $order->returns->firstWhere('id', session('return_credit_note_id'));
                $autoCreditUrl = route('admin.orders.returns.creditNote', [$order->id, session('return_credit_note_id')], false);
                $autoCreditName = 'credit-note-'.($autoReturn?->return_number ?? 'return').'.pdf';
            @endphp
            <div class="hidden"
                data-no-loader
                data-auto-credit-note
                data-pdf-url="{{ $autoCreditUrl }}"
                data-pdf-filename="{{ $autoCreditName }}"></div>
            <script>
                (function () {
                    var el = document.querySelector('[data-auto-credit-note]');
                    if (!el || el.dataset.started) return;
                    el.dataset.started = '1';
                    var start = function () {
                        if (typeof window.dokannwardDownloadPdf === 'function') {
                            window.dokannwardDownloadPdf(el);
                            return;
                        }
                        window.setTimeout(start, 50);
                    };
                    start();
                })();
            </script>
        @endif

        {{-- Hero --}}
        <div class="order-sheet order-panel overflow-hidden">
            <div class="px-5 sm:px-8 pt-7 pb-6">
                <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-6">
                    <div class="min-w-0">
                        <p class="order-label mb-2">Order confirmation</p>
                        <h1 class="text-3xl sm:text-[2.5rem] font-semibold tracking-tight text-zibra-ink dark:text-white order-mono leading-none">
                            {{ $order->order_number }}
                        </h1>
                        <p class="mt-3 text-sm text-zibra-ash flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span>Placed <span class="text-zibra-ink dark:text-white font-medium">{{ $placedAt->format('D, M j, Y') }}</span>
                            at <span class="text-zibra-ink dark:text-white font-medium">{{ $placedAt->format('g:i A') }}</span></span>
                            <span class="text-zibra-line">·</span>
                            <span>{{ $placedAt->diffForHumans() }}</span>
                            @if ($isFullyRefunded)
                                <span class="text-zibra-line">·</span>
                                <x-admin.badge variant="danger">Fully refunded</x-admin.badge>
                            @elseif ($isPartiallyRefunded)
                                <span class="text-zibra-line">·</span>
                                <x-admin.badge variant="warning">Partially refunded</x-admin.badge>
                            @endif
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 xl:gap-4 w-full xl:w-auto xl:min-w-[28rem]">
                        <div class="order-kpi">
                            <p class="order-label mb-1">{{ $refundedTotal > 0 ? 'Net total' : 'Order total' }}</p>
                            <p class="text-xl font-semibold tracking-tight text-zibra-ink dark:text-white tabular-nums">
                                {{ \App\Support\Money::format($netTotal) }}
                            </p>
                        </div>
                        <div class="order-kpi">
                            <p class="order-label mb-1">Items</p>
                            <p class="text-xl font-semibold tracking-tight text-zibra-ink dark:text-white tabular-nums">
                                {{ $itemQty }}
                            </p>
                        </div>
                        <div class="order-kpi col-span-2 sm:col-span-1" data-tone="{{ $statusTone }}">
                            <p class="order-label mb-1">Status</p>
                            <p class="text-xl font-semibold tracking-tight text-zibra-ink dark:text-white">
                                {{ ucfirst($order->status) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-rule"></div>

            <div class="grid grid-cols-1 xl:grid-cols-2">
                <div class="px-5 sm:px-8 py-6 border-b xl:border-b-0 xl:border-r border-zibra-line dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <p class="order-label mb-0">Client</p>
                        @unless ($order->user)
                            <span class="text-[10px] uppercase tracking-[0.14em] text-zibra-ash">Guest checkout</span>
                        @endunless
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 shrink-0 bg-zibra-ink text-white flex items-center justify-center text-lg font-semibold">
                            {{ $initial }}
                        </div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <p class="text-xl font-semibold text-zibra-ink dark:text-white leading-tight">{{ $clientName }}</p>

                            <div class="flex flex-wrap gap-2">
                                @if ($clientPhone)
                                    <a href="tel:{{ $clientPhone }}" class="order-contact">
                                        <i class="fas fa-phone text-[10px]" aria-hidden="true"></i>
                                        Call
                                    </a>
                                @endif
                                @if ($waPhone)
                                    <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener" class="order-contact">
                                        <i class="fab fa-whatsapp text-[12px]" aria-hidden="true"></i>
                                        WhatsApp
                                    </a>
                                @endif
                                @if ($clientEmail)
                                    <a href="mailto:{{ $clientEmail }}" class="order-contact">
                                        <i class="fas fa-envelope text-[10px]" aria-hidden="true"></i>
                                        Email
                                    </a>
                                @endif
                            </div>

                            <dl class="space-y-2 pt-1">
                                @if ($clientPhone)
                                    <div class="flex items-baseline gap-3">
                                        <dt class="order-label w-14 shrink-0">Phone</dt>
                                        <dd class="text-sm text-zibra-ink dark:text-white">{{ $clientPhone }}</dd>
                                    </div>
                                @endif
                                @if ($clientEmail)
                                    <div class="flex items-baseline gap-3">
                                        <dt class="order-label w-14 shrink-0">Email</dt>
                                        <dd class="text-sm text-zibra-ink dark:text-white truncate">{{ $clientEmail }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-8 py-6">
                    <p class="order-label mb-5">Shipping address</p>
                    @if ($address)
                        <div class="space-y-4">
                            @if ($address->hasCoordinates())
                                <a href="{{ $address->googleMapsUrl() }}" target="_blank" rel="noopener"
                                    class="order-pin-map" title="Open in Google Maps">
                                    <img src="{{ $address->osmTileUrl(16) }}" alt="Delivery pin map" width="640" height="220">
                                    <span class="order-pin-map__dot" aria-hidden="true"></span>
                                    <span class="order-pin-map__open">Open map</span>
                                </a>
                            @endif
                            <div class="space-y-1 text-sm leading-relaxed text-zibra-ink dark:text-white">
                                <p class="font-medium text-base">{{ $address->recipient_name }}</p>
                                <p>{{ $address->line_1 }}</p>
                                @if ($address->line_2)
                                    <p>{{ $address->line_2 }}</p>
                                @endif
                                <p>
                                    {{ $address->city }}@if ($address->state), {{ $address->state }}@endif
                                    @if ($address->postal_code)
                                        <span class="text-zibra-ash">{{ $address->postal_code }}</span>
                                    @endif
                                </p>
                                <p class="text-zibra-ash">{{ $address->country }}</p>
                                @if ($address->place_name)
                                    <p class="text-zibra-ash pt-1">{{ $address->place_name }}</p>
                                @endif
                                @if ($address->hasCoordinates())
                                    <p class="order-mono text-xs text-zibra-ash pt-1">
                                        {{ number_format((float) $address->latitude, 5) }},
                                        {{ number_format((float) $address->longitude, 5) }}
                                    </p>
                                    <div class="flex flex-wrap gap-2 pt-2">
                                        <a href="{{ $address->googleMapsUrl() }}" target="_blank" rel="noopener" class="order-contact">
                                            Google Maps
                                        </a>
                                        <a href="{{ $address->appleMapsUrl() }}" target="_blank" rel="noopener" class="order-contact">
                                            Apple Maps
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-zibra-ash">No shipping address on file.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-8 space-y-5">
                {{-- Line items --}}
                <div class="order-panel">
                    <div class="px-5 sm:px-8 py-4 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <p class="order-label mb-0.5">Catalog</p>
                            <h2 class="text-base font-semibold text-zibra-ink dark:text-white tracking-tight">Items ordered</h2>
                        </div>
                        <p class="text-xs text-zibra-ash">{{ $order->items->count() }} line{{ $order->items->count() === 1 ? '' : 's' }} · {{ $itemQty }} qty</p>
                    </div>

                    <div class="divide-y divide-zibra-line dark:divide-gray-700">
                        @foreach ($order->items as $item)
                            @php
                                $thumb = $item->variant?->photos?->first()?->url
                                    ?? $item->product?->photos?->firstWhere('is_primary', true)?->url
                                    ?? $item->product?->photos?->first()?->url;
                            @endphp
                            <div class="px-5 sm:px-8 py-5 flex gap-4 sm:gap-5">
                                <div class="shrink-0 border border-zibra-line dark:border-gray-700 overflow-hidden bg-zibra-paper dark:bg-gray-900">
                                    @if ($thumb)
                                        <img src="{{ $thumb }}" alt="" class="order-thumb" loading="lazy">
                                    @else
                                        <div class="order-thumb flex items-center justify-center text-zibra-ash">
                                            <i class="fas fa-shopping-bag text-sm"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="font-medium text-zibra-ink dark:text-white leading-snug">{{ $item->name }}</h3>
                                            @if ($item->variant?->color)
                                                <p class="mt-1.5 flex items-center gap-2 text-sm text-zibra-ash">
                                                    <span class="inline-block w-3 h-3 rounded-full border border-zibra-line"
                                                        style="background-color: {{ $item->variant->color->hex }}"></span>
                                                    {{ $item->variant->color->name }}
                                                </p>
                                            @endif
                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zibra-ash">
                                                @if ($item->sku)
                                                    <span class="order-mono">SKU {{ $item->sku }}</span>
                                                @endif
                                                <span>Qty {{ $item->qty }}</span>
                                                <span>{{ \App\Support\Money::format($item->unit_price) }} each</span>
                                            </div>
                                        </div>
                                        <p class="text-base font-semibold text-zibra-ink dark:text-white tabular-nums shrink-0 sm:text-right">
                                            {{ \App\Support\Money::format($item->line_total) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-5 sm:px-8 py-5 border-t border-zibra-line dark:border-gray-700 bg-[#fafafa] dark:bg-gray-900/30">
                        <div class="w-full max-w-sm ml-auto space-y-2.5 text-sm">
                            <div class="flex justify-between text-zibra-ash">
                                <span>Subtotal</span>
                                <span class="tabular-nums text-zibra-ink dark:text-white">{{ \App\Support\Money::format($order->subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-zibra-ash">
                                <span>Shipping</span>
                                <span class="tabular-nums text-zibra-ink dark:text-white">
                                    {{ (float) $order->shipping_amount > 0 ? \App\Support\Money::format($order->shipping_amount) : 'Complimentary' }}
                                </span>
                            </div>
                            @if ((float) $order->tax_amount > 0)
                                <div class="flex justify-between text-zibra-ash">
                                    <span>Tax</span>
                                    <span class="tabular-nums text-zibra-ink dark:text-white">{{ \App\Support\Money::format($order->tax_amount) }}</span>
                                </div>
                            @endif
                            <div class="order-rule my-3"></div>
                            <div class="flex justify-between items-baseline">
                                <span class="order-label">Total</span>
                                <span class="text-xl font-semibold text-zibra-ink dark:text-white tabular-nums">
                                    {{ \App\Support\Money::format($order->total_amount) }}
                                </span>
                            </div>
                            @if ($refundedTotal > 0)
                                <div class="flex justify-between text-zibra-ash pt-1">
                                    <span>Refunded</span>
                                    <span class="tabular-nums text-red-600 dark:text-red-400">−{{ \App\Support\Money::format($refundedTotal) }}</span>
                                </div>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs uppercase tracking-[0.14em] text-zibra-ash">Net</span>
                                    <span class="text-base font-semibold text-zibra-ink dark:text-white tabular-nums">
                                        {{ \App\Support\Money::format($netTotal) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Returns --}}
                <div class="order-panel">
                    <div class="px-5 sm:px-8 py-4 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between gap-3">
                        <div>
                            <p class="order-label mb-0.5">Returns</p>
                            <h2 class="text-base font-semibold text-zibra-ink dark:text-white tracking-tight">Credit notes</h2>
                        </div>
                        @if ($refundableRemaining > 0)
                            <a href="{{ route('admin.orders.returns.create', $order->id) }}" class="order-btn order-btn--primary">
                                New return
                            </a>
                        @endif
                    </div>

                    @if ($order->returns->isEmpty())
                        <div class="px-5 sm:px-8 py-10 text-center">
                            <p class="text-sm text-zibra-ash">No returns yet. Issue a credit note when the customer sends items back.</p>
                        </div>
                    @else
                        <div class="divide-y divide-zibra-line dark:divide-gray-700">
                            @foreach ($order->returns as $ret)
                                <div class="px-5 sm:px-8 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-zibra-ink dark:text-white order-mono">{{ $ret->return_number }}</p>
                                            <x-admin.badge variant="success">{{ ucfirst($ret->status) }}</x-admin.badge>
                                        </div>
                                        <p class="mt-1.5 text-xs text-zibra-ash">
                                            {{ ($ret->refunded_at ?? $ret->created_at)->format('M d, Y · h:i A') }}
                                            @if ($ret->processedBy)
                                                · {{ $ret->processedBy->name }}
                                            @endif
                                            @if ($ret->reason)
                                                · {{ $reasonLabels[$ret->reason] ?? ucfirst(str_replace('_', ' ', $ret->reason)) }}
                                            @endif
                                            @if ($ret->items->isNotEmpty())
                                                · {{ $ret->items->sum('qty') }} {{ Str::plural('item', $ret->items->sum('qty')) }}
                                            @endif
                                            @if ($ret->restock)
                                                · restocked
                                            @endif
                                        </p>
                                        @if ($ret->notes)
                                            <p class="mt-1 text-xs text-zibra-ash truncate">{{ $ret->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0">
                                        <p class="text-base font-semibold tabular-nums text-zibra-ink dark:text-white">
                                            {{ \App\Support\Money::format($ret->refund_amount) }}
                                        </p>
                                        <button type="button"
                                            data-no-loader
                                            data-pdf-url="{{ route('admin.orders.returns.creditNote', [$order->id, $ret->id], false) }}"
                                            data-pdf-filename="credit-note-{{ $ret->return_number }}.pdf"
                                            x-data="{ loading: false }"
                                            @click="loading = true; window.dokannwardDownloadPdf($el).finally(() => loading = false)"
                                            :disabled="loading"
                                            class="order-btn relative min-w-[7.5rem]">
                                            <span :class="loading && 'invisible'">Credit note</span>
                                            <span x-show="loading" x-cloak class="absolute inset-0 flex items-center justify-center">…</span>
                                        </button>
                                        <form method="POST" action="{{ route('admin.orders.returns.destroy', [$order->id, $ret->id]) }}"
                                            data-confirm="Delete this return record?" data-confirm-title="Delete return?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="order-btn order-btn--danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sidebar --}}
            <aside class="xl:col-span-4 space-y-5 order-side-sticky">
                <div class="order-status-card" data-tone="{{ $statusTone }}">
                    <p class="order-label mb-0">Status</p>
                    <p class="order-status-card__value">{{ ucfirst($order->status) }}</p>
                    <p class="order-status-card__hint">
                        Updated {{ $order->updated_at->diffForHumans() }}
                        @if ($isPartiallyRefunded)
                            · Partially refunded
                        @elseif ($isFullyRefunded)
                            · Fully refunded
                        @endif
                    </p>
                </div>

                @if ($order->notes)
                    <div class="order-panel">
                        <div class="px-5 py-3.5 border-b border-zibra-line dark:border-gray-700">
                            <p class="order-label">Client notes</p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-sm leading-relaxed text-zibra-ink dark:text-white whitespace-pre-wrap">{{ $order->notes }}</p>
                        </div>
                    </div>
                @endif

                @if ($order->delivery_tracking_number)
                    <div class="order-panel">
                        <div class="px-5 py-3.5 border-b border-zibra-line dark:border-gray-700">
                            <p class="order-label">Tracking</p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="order-mono text-sm text-zibra-ink dark:text-white">{{ $order->delivery_tracking_number }}</p>
                            @if ($order->fulfillment_provider)
                                <p class="mt-1 text-xs text-zibra-ash">{{ $order->fulfillment_provider }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="order-panel">
                    <div class="px-5 py-3.5 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between gap-2">
                        <p class="order-label mb-0">Timeline</p>
                        <a href="{{ route('admin.orders.edit', $order->id) }}" class="text-[10px] uppercase tracking-[0.14em] text-zibra-ash hover:text-zibra-ink dark:hover:text-white">
                            Edit desk
                        </a>
                    </div>
                    <div class="px-5 py-5">
                        <ol class="relative border-s border-zibra-line dark:border-gray-700 ms-2 space-y-5">
                            <li class="ms-5">
                                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-zibra-ink dark:bg-white"></span>
                                <p class="text-sm font-medium text-zibra-ink dark:text-white">Order placed</p>
                                <p class="text-xs text-zibra-ash mt-0.5">{{ $placedAt->format('M d, Y · g:i A') }}</p>
                            </li>
                            @if ($order->confirmed_at)
                                <li class="ms-5">
                                    <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-zibra-ash"></span>
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white">Confirmed</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">{{ $order->confirmed_at->format('M d, Y · g:i A') }}</p>
                                </li>
                            @endif
                            @if ($order->shipped_at)
                                <li class="ms-5">
                                    <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-blue-500"></span>
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white">Shipped</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">{{ $order->shipped_at->format('M d, Y · g:i A') }}</p>
                                </li>
                            @endif
                            @if ($order->delivered_at)
                                <li class="ms-5">
                                    <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-green-600"></span>
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white">Delivered</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">{{ $order->delivered_at->format('M d, Y · g:i A') }}</p>
                                </li>
                            @endif
                            @if ($order->canceled_at)
                                <li class="ms-5">
                                    <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-red-500"></span>
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white">Cancelled</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">
                                        {{ $order->canceled_at->format('M d, Y · g:i A') }}
                                        @if ($order->canceledBy)
                                            · {{ $order->canceledBy->name }}
                                        @endif
                                    </p>
                                </li>
                            @endif
                            @foreach ($order->statusHistories->take(8) as $entry)
                                <li class="ms-5">
                                    <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-zibra-line dark:border-gray-500 bg-white dark:bg-gray-800"></span>
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white">Audit · {{ ucfirst($entry->status) }}</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">
                                        {{ $entry->created_at?->format('M d, Y · g:i A') }}
                                        @if ($entry->changedBy)
                                            · {{ $entry->changedBy->name }}
                                        @endif
                                    </p>
                                    @if ($entry->note)
                                        <p class="text-xs text-zibra-ash mt-1">{{ $entry->note }}</p>
                                    @endif
                                </li>
                            @endforeach
                            <li class="ms-5">
                                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-zibra-ink dark:border-white bg-white dark:bg-gray-800"></span>
                                <p class="text-sm font-medium text-zibra-ink dark:text-white">Current · {{ ucfirst($order->status) }}</p>
                                <p class="text-xs text-zibra-ash mt-0.5">Updated {{ $order->updated_at->diffForHumans() }}</p>
                            </li>
                        </ol>
                    </div>
                </div>

                @unless ($order->notes && $order->delivery_tracking_number)
                    <div class="order-panel px-5 py-4">
                        <p class="order-label mb-2">Quick desk</p>
                        <p class="text-xs text-zibra-ash mb-3">Add tracking or notes without leaving the order sheet.</p>
                        <a href="{{ route('admin.orders.edit', $order->id) }}" class="order-btn order-btn--primary w-full">
                            Update notes / tracking
                        </a>
                    </div>
                @endunless

                <div class="order-panel px-5 py-4">
                    <p class="order-label mb-3">Order meta</p>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zibra-ash">Lines</dt>
                            <dd class="text-zibra-ink dark:text-white tabular-nums">{{ $order->items->count() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zibra-ash">Quantity</dt>
                            <dd class="text-zibra-ink dark:text-white tabular-nums">{{ $itemQty }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zibra-ash">Checkout</dt>
                            <dd class="text-zibra-ink dark:text-white">{{ $order->user ? 'Account' : 'Guest' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zibra-ash">Payment</dt>
                            <dd class="text-zibra-ink dark:text-white text-right">
                                {{ \App\Services\OrderPlacementService::paymentLabel($order->payment_method) ?? 'Not recorded' }}
                            </dd>
                        </div>
                        @if ($refundableRemaining > 0 || $refundedTotal > 0)
                            <div class="flex justify-between gap-3">
                                <dt class="text-zibra-ash">Refundable</dt>
                                <dd class="text-zibra-ink dark:text-white tabular-nums">{{ \App\Support\Money::format($refundableRemaining) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.dokannwardDownloadPdf = function (el) {
        if (!el) return Promise.reject(new Error('Missing element'));
        var url = el.getAttribute('data-pdf-url');
        var fallbackName = el.getAttribute('data-pdf-filename') || 'download.pdf';
        if (!url) return Promise.reject(new Error('Missing PDF URL'));

        return fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/pdf',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) throw new Error('Download failed (' + response.status + ')');
            var disposition = response.headers.get('Content-Disposition') || '';
            var match = disposition.match(/filename\*=UTF-8''([^;\s]+)|filename="?([^";]+)"?/i);
            var filename = fallbackName;
            if (match) {
                try {
                    filename = decodeURIComponent(match[1] || match[2] || fallbackName);
                } catch (e) {
                    filename = match[1] || match[2] || fallbackName;
                }
            }
            filename = String(filename).replace(/["']/g, '').trim() || fallbackName;

            return response.blob().then(function (blob) {
                // Guard against HTML error pages saved as .pdf
                if (blob.type && blob.type.indexOf('pdf') === -1 && blob.type.indexOf('octet-stream') === -1) {
                    throw new Error('Unexpected response type: ' + blob.type);
                }
                var objectUrl = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1500);
            });
        }).catch(function () {
            // Same-origin relative URL — browser handles Content-Disposition: attachment
            window.location.assign(url);
        });
    };

    (function () {
        var bar = document.querySelector('[data-order-toolbar]');
        if (!bar || !('IntersectionObserver' in window)) return;
        var sentinel = document.createElement('div');
        sentinel.style.position = 'absolute';
        sentinel.style.top = '0';
        sentinel.style.height = '1px';
        sentinel.style.width = '1px';
        sentinel.style.pointerEvents = 'none';
        bar.parentNode.insertBefore(sentinel, bar);
        var io = new IntersectionObserver(function (entries) {
            bar.classList.toggle('is-stuck', !entries[0].isIntersecting);
        }, { threshold: [1] });
        io.observe(sentinel);
    })();
</script>
@endpush
