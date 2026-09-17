@extends('admin.layouts.app')

@section('title', 'Dashboard')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $adminName = auth()->user()?->name ?? 'Administrator';
@endphp

@push('styles')
    <style>
        .dash {
            --dash-ease: cubic-bezier(0.16, 1, 0.3, 1);
            /* Oxblood garnet — luxury accent against Dokan Ward ink/paper */
            --dash-red: #9e1b2e;
            --dash-red-deep: #7a1423;
            --dash-red-soft: #c45a68;
            --dash-red-mist: rgb(158 27 46 / 0.1);
            --dash-red-glow: rgb(158 27 46 / 0.42);
        }

        .dash-reveal {
            opacity: 0;
            transform: translateY(18px);
            animation: dash-rise 0.9s var(--dash-ease) forwards;
        }

        .dash-reveal-d1 { animation-delay: 0.04s; }
        .dash-reveal-d2 { animation-delay: 0.1s; }
        .dash-reveal-d3 { animation-delay: 0.16s; }
        .dash-reveal-d4 { animation-delay: 0.22s; }
        .dash-reveal-d5 { animation-delay: 0.28s; }
        .dash-reveal-d6 { animation-delay: 0.34s; }
        .dash-reveal-d7 { animation-delay: 0.4s; }
        .dash-reveal-d8 { animation-delay: 0.46s; }

        @keyframes dash-rise {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes dash-stripe {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }

        @keyframes dash-pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgb(158 27 46 / 0.55); }
            50% { opacity: 0.7; transform: scale(0.88); box-shadow: 0 0 0 7px rgb(158 27 46 / 0); }
        }

        .dash-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid #dcdcdc;
            background:
                radial-gradient(ellipse 55% 80% at 100% 0%, rgb(158 27 46 / 0.28), transparent 55%),
                linear-gradient(135deg, #0a0a0a 0%, #141014 42%, #1c1214 72%, #2a1a1e 100%);
            color: #fff;
        }

        .dark .dash-hero {
            border-color: rgb(55 65 81);
        }

        .dash-hero__stripes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.09;
            background:
                repeating-linear-gradient(
                    -18deg,
                    transparent 0,
                    transparent 10px,
                    #fff 10px,
                    #fff 11px
                );
        }

        .dash-hero__glow {
            position: absolute;
            width: 420px;
            height: 420px;
            right: -120px;
            top: -160px;
            border-radius: 9999px;
            background: radial-gradient(circle, var(--dash-red-glow), transparent 68%);
            pointer-events: none;
        }

        .dash-hero__accent {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--dash-red-soft), var(--dash-red) 40%, var(--dash-red-deep));
        }

        .dash-hero__rule {
            height: 1px;
            background: linear-gradient(
                90deg,
                var(--dash-red) 0%,
                rgb(255 255 255 / 0.22) 28%,
                rgb(255 255 255 / 0.08) 100%
            );
            transform-origin: left;
            animation: dash-stripe 1.1s var(--dash-ease) 0.2s both;
        }

        .dash-label {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .dash-panel {
            border: 1px solid #dcdcdc;
            background: #fff;
            transition: border-color 0.35s var(--dash-ease), transform 0.45s var(--dash-ease), box-shadow 0.45s var(--dash-ease);
        }

        .dark .dash-panel {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .dash-panel:hover {
            border-color: rgb(158 27 46 / 0.45);
            box-shadow: 0 0 0 1px var(--dash-red-mist);
        }

        .dark .dash-panel:hover {
            border-color: rgb(196 90 104 / 0.55);
        }

        .dash-stat {
            position: relative;
            overflow: hidden;
        }

        .dash-stat::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--dash-red), var(--dash-red-deep));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.55s var(--dash-ease);
        }

        .dark .dash-stat::after {
            background: linear-gradient(90deg, var(--dash-red-soft), var(--dash-red));
        }

        .dash-stat:hover::after {
            transform: scaleX(1);
        }

        .dash-stat__value {
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.03em;
        }

        .dash-live {
            width: 7px;
            height: 7px;
            border-radius: 9999px;
            background: var(--dash-red-soft);
            box-shadow: 0 0 0 0 rgb(158 27 46 / 0.45);
            animation: dash-pulse-dot 1.8s ease-in-out infinite;
        }

        .dash-hero__tile--alert {
            background: linear-gradient(160deg, rgb(158 27 46 / 0.35), rgb(0 0 0 / 0.35));
            box-shadow: inset 0 0 0 1px rgb(196 90 104 / 0.35);
        }

        .dash-hero__tile--alert .fit-num {
            color: #ffd6db;
        }

        .dash-chart-wrap {
            position: relative;
            height: 280px;
        }

        .dash-chart-wrap--sm {
            height: 240px;
        }

        .dash-row {
            border-bottom: 1px solid #ececec;
            transition: background 0.3s var(--dash-ease);
        }

        .dark .dash-row {
            border-bottom-color: rgb(55 65 81);
        }

        .dash-row:last-child {
            border-bottom: 0;
        }

        .dash-row:hover {
            background: linear-gradient(90deg, var(--dash-red-mist), #fafafa 42%);
        }

        .dark .dash-row:hover {
            background: linear-gradient(90deg, rgb(158 27 46 / 0.14), rgb(17 24 39 / 0.55) 45%);
        }

        .dash-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            background: #fafafa;
            border: 1px solid #dcdcdc;
        }

        .dark .dash-thumb {
            background: rgb(17 24 39);
            border-color: rgb(55 65 81);
        }

        .dash-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.85rem;
            font-size: 10px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #0a0a0a;
            background: #fff;
            border: 1px solid #dcdcdc;
            text-decoration: none;
            transition:
                color 0.25s var(--dash-ease),
                border-color 0.25s var(--dash-ease),
                background 0.25s var(--dash-ease),
                transform 0.25s var(--dash-ease),
                box-shadow 0.25s var(--dash-ease);
        }

        .dash-link i {
            transition: transform 0.3s var(--dash-ease);
        }

        .dash-link:hover {
            color: #fff;
            background: var(--dash-red);
            border-color: var(--dash-red);
            box-shadow: 0 8px 22px rgb(158 27 46 / 0.22);
            transform: translateY(-1px);
        }

        .dash-link:hover i {
            transform: translateX(3px);
        }

        .dark .dash-link {
            color: #fff;
            background: rgb(17 24 39);
            border-color: rgb(55 65 81);
        }

        .dark .dash-link:hover {
            color: #fff;
            background: var(--dash-red);
            border-color: var(--dash-red);
        }

        .dash-hero__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 1.25rem;
        }

        .dash-hero__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem 0.85rem;
            font-size: 10px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgb(255 255 255 / 0.42);
        }

        .dash-hero__meta-dot {
            width: 3px;
            height: 3px;
            border-radius: 9999px;
            background: rgb(255 255 255 / 0.28);
        }

        .dash-hero__ctas {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
        }

        .dash-hero__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            min-height: 2.35rem;
            padding: 0.55rem 1.05rem;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-decoration: none;
            border: 1px solid transparent;
            transition:
                background 0.28s var(--dash-ease),
                color 0.28s var(--dash-ease),
                border-color 0.28s var(--dash-ease),
                box-shadow 0.28s var(--dash-ease),
                transform 0.28s var(--dash-ease);
        }

        .dash-hero__btn i {
            font-size: 11px;
            transition: transform 0.3s var(--dash-ease);
        }

        .dash-hero__btn--primary {
            color: #0a0a0a;
            background: #fff;
            border-color: #fff;
            box-shadow: 0 10px 28px rgb(0 0 0 / 0.28);
        }

        .dash-hero__btn--primary:hover {
            color: #fff;
            background: var(--dash-red);
            border-color: var(--dash-red);
            box-shadow: 0 12px 30px rgb(158 27 46 / 0.38);
            transform: translateY(-1px);
        }

        .dash-hero__btn--primary:hover i {
            transform: translateX(3px);
        }

        .dash-hero__btn--ghost {
            color: rgb(255 255 255 / 0.92);
            background: rgb(255 255 255 / 0.04);
            border-color: rgb(255 255 255 / 0.28);
            backdrop-filter: blur(8px);
        }

        .dash-hero__btn--ghost:hover {
            color: #fff;
            background: rgb(255 255 255 / 0.12);
            border-color: rgb(255 214 219 / 0.65);
            box-shadow: 0 10px 24px rgb(0 0 0 / 0.22);
            transform: translateY(-1px);
        }

        .dash-hero__btn--ghost:hover i {
            transform: translate(2px, -2px);
        }

        .dash-hero__btn:focus-visible {
            outline: 2px solid #ffd6db;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            .dash-link,
            .dash-link i,
            .dash-hero__btn,
            .dash-hero__btn i {
                transition: none !important;
            }

            .dash-link:hover,
            .dash-hero__btn:hover {
                transform: none !important;
            }
        }

        .dash-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            border: 1px solid currentColor;
        }

        .dash-badge--pending { color: #6b6b6b; }
        .dash-badge--processing { color: var(--dash-red-deep); border-color: var(--dash-red); }
        .dash-badge--shipped { color: #0a0a0a; }
        .dash-badge--delivered { color: #166534; border-color: #166534; }
        .dash-badge--cancelled { color: var(--dash-red); border-color: var(--dash-red); }

        .dash-trend--positive { color: #166534; }
        .dash-trend--negative { color: var(--dash-red); }
        .dash-trend--neutral { color: #6b6b6b; }

        .dash-meter {
            height: 2px;
            background: #ececec;
            overflow: hidden;
        }

        .dark .dash-meter {
            background: rgb(55 65 81);
        }

        .dash-meter > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #0a0a0a 0%, #0a0a0a 55%, var(--dash-red));
            transform-origin: left;
            animation: dash-stripe 1s var(--dash-ease) both;
        }

        .dark .dash-meter > span {
            background: linear-gradient(90deg, #fff 0%, #fff 50%, var(--dash-red-soft));
        }

        .dash-chart-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .dash-section-mark {
            display: inline-block;
            width: 18px;
            height: 2px;
            background: var(--dash-red);
            margin-bottom: 0.55rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .dash-reveal,
            .dash-hero__rule,
            .dash-live,
            .dash-meter > span {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
                box-shadow: none !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="dash space-y-7 w-full">
        {{-- Hero --}}
        <section class="dash-hero dash-reveal">
            <div class="dash-hero__accent" aria-hidden="true"></div>
            <div class="dash-hero__stripes" aria-hidden="true"></div>
            <div class="dash-hero__glow" aria-hidden="true"></div>

            <div class="relative px-6 sm:px-8 pt-8 pb-7">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-3 mb-5">
                            <img src="{{ ($brandLogoOnDarkUrl ?? asset('images/brand-logo-on-dark.png')) }}"
                                alt="{{ $brandDisplayName ?? 'Store' }}" class="h-7 w-auto opacity-95">
                            <span class="dash-live" aria-hidden="true"></span>
                            <span class="text-[10px] uppercase tracking-[0.22em] text-white/45">Live commerce</span>
                        </div>

                        <p class="text-[11px] uppercase tracking-[0.22em] text-white/45 mb-3">{{ $greeting }}</p>
                        <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight text-white leading-tight">
                            {{ $adminName }}
                        </h1>
                        <p class="mt-3 text-sm text-white/65 max-w-lg leading-relaxed">
                            {{ $brandDisplayName ?? 'Store' }} Admin — a precise read of revenue, orders, and inventory, aligned with the storefront experience.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-white/10 border border-white/10 min-w-0 lg:min-w-[420px]">
                        <div class="box-fit bg-black/30 px-4 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-white/40">Today</p>
                            <p class="fit-num mt-1 font-semibold" style="--fit-num-max: 1.125rem">{{ $ordersToday }}</p>
                            <p class="text-[10px] text-white/40 uppercase tracking-[0.12em]">Orders</p>
                        </div>
                        <div class="box-fit bg-black/30 px-4 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-white/40">Today</p>
                            <p class="fit-num mt-1 font-semibold" style="--fit-num-max: 1.125rem">{{ \App\Support\Money::format($revenueToday) }}</p>
                            <p class="text-[10px] text-white/40 uppercase tracking-[0.12em]">Revenue</p>
                        </div>
                        <div class="box-fit px-4 py-3 {{ $pendingOrders > 0 ? 'dash-hero__tile--alert' : 'bg-black/30' }}">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-white/40">Pending</p>
                            <p class="fit-num mt-1 font-semibold" style="--fit-num-max: 1.125rem">{{ $pendingOrders }}</p>
                            <p class="text-[10px] text-white/40 uppercase tracking-[0.12em]">Orders</p>
                        </div>
                        <div class="box-fit bg-black/30 px-4 py-3">
                            <p class="text-[9px] uppercase tracking-[0.18em] text-white/40">Avg order</p>
                            <p class="fit-num mt-1 font-semibold" style="--fit-num-max: 1.125rem">{{ \App\Support\Money::format($avgOrderValue) }}</p>
                            <p class="text-[10px] text-white/40 uppercase tracking-[0.12em]">Value</p>
                        </div>
                    </div>
                </div>

                <div class="dash-hero__rule mt-8"></div>

                <div class="dash-hero__actions mt-5">
                    <div class="dash-hero__meta">
                        <span>{{ now()->format('l · M d, Y') }}</span>
                        <span class="dash-hero__meta-dot" aria-hidden="true"></span>
                        <span>{{ $processingOrders }} processing</span>
                    </div>
                    <div class="dash-hero__ctas">
                        <a href="{{ route('admin.orders.index') }}" class="dash-hero__btn dash-hero__btn--primary">
                            View orders
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-no-loader
                            class="dash-hero__btn dash-hero__btn--ghost">
                            View storefront
                            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- KPI strip --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @php
                $kpis = [
                    [
                        'label' => 'Total revenue',
                        'value' => \App\Support\Money::format($totalRevenue),
                        'trend' => $revenueTrend,
                        'href' => route('admin.orders.index'),
                        'hint' => 'Non-cancelled orders',
                        'delay' => 'dash-reveal-d1',
                    ],
                    [
                        'label' => 'Total orders',
                        'value' => number_format($totalOrders),
                        'trend' => $ordersTrend,
                        'href' => route('admin.orders.index'),
                        'hint' => 'All statuses',
                        'delay' => 'dash-reveal-d2',
                    ],
                    [
                        'label' => 'Catalog',
                        'value' => number_format($totalProducts),
                        'trend' => $productsTrend,
                        'href' => route('admin.products.index'),
                        'hint' => 'Active products',
                        'delay' => 'dash-reveal-d3',
                    ],
                    [
                        'label' => 'Customers',
                        'value' => number_format($totalCustomers),
                        'trend' => $customersTrend,
                        'href' => route('admin.customers.index'),
                        'hint' => 'Registered accounts',
                        'delay' => 'dash-reveal-d4',
                    ],
                ];
            @endphp

            @foreach ($kpis as $kpi)
                <a href="{{ $kpi['href'] }}"
                    class="box-fit dash-panel dash-stat dash-reveal {{ $kpi['delay'] }} block px-5 py-5 no-underline group">
                    <div class="flex items-start justify-between gap-3 min-w-0">
                        <p class="dash-label fit-text">{{ $kpi['label'] }}</p>
                        <span class="shrink-0 text-[10px] uppercase tracking-[0.14em] dash-trend--{{ $kpi['trend']['direction'] }}">
                            {{ $kpi['trend']['label'] }}
                        </span>
                    </div>
                    <p class="dash-stat__value fit-num fit-num--lg mt-4 font-semibold text-zibra-ink dark:text-white">
                        {{ $kpi['value'] }}
                    </p>
                    <p class="mt-2 text-xs text-zibra-ash">
                        {{ $kpi['hint'] }}
                        <span class="text-zibra-line mx-1.5">·</span>
                        vs last month
                    </p>
                </a>
            @endforeach
        </section>

        {{-- Charts --}}
        <section class="grid grid-cols-1 xl:grid-cols-5 gap-4">
            <div class="dash-panel dash-reveal dash-reveal-d5 xl:col-span-3 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700 flex items-end justify-between gap-4">
                    <div>
                        <span class="dash-section-mark" aria-hidden="true"></span>
                        <p class="dash-label mb-1">Revenue overview</p>
                        <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Last 12 months</h2>
                    </div>
                    <p class="text-xs text-zibra-ash tabular-nums">{{ \App\Support\Money::format($totalRevenue) }} lifetime</p>
                </div>
                <div class="px-4 sm:px-6 py-5">
                    <div class="dash-chart-wrap">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dash-panel dash-reveal dash-reveal-d6 xl:col-span-2 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700">
                    <span class="dash-section-mark" aria-hidden="true"></span>
                    <p class="dash-label mb-1">Orders volume</p>
                    <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Monthly demand</h2>
                </div>
                <div class="px-4 sm:px-6 py-5">
                    <div class="dash-chart-wrap">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        {{-- Category + recent orders --}}
        <section class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="dash-panel dash-reveal dash-reveal-d7 xl:col-span-4 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700">
                    <span class="dash-section-mark" aria-hidden="true"></span>
                    <p class="dash-label mb-1">Sales by category</p>
                    <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Mix share</h2>
                </div>
                <div class="px-4 sm:px-6 py-5">
                    <div class="dash-chart-wrap dash-chart-wrap--sm">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="dash-panel dash-reveal dash-reveal-d8 xl:col-span-8 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between gap-4">
                    <div>
                        <span class="dash-section-mark" aria-hidden="true"></span>
                        <p class="dash-label mb-1">Recent orders</p>
                        <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Latest activity</h2>
                    </div>
                    <a href="{{ route('admin.orders.index') }}" class="dash-link inline-flex items-center gap-2">
                        View all <i class="fas fa-arrow-right text-[9px]"></i>
                    </a>
                </div>

                <div>
                    @forelse ($recentOrders as $order)
                        @php
                            $status = $order->status ?? 'pending';
                            $badgeClass = match ($status) {
                                'delivered' => 'dash-badge--delivered',
                                'cancelled' => 'dash-badge--cancelled',
                                'shipped' => 'dash-badge--shipped',
                                'processing' => 'dash-badge--processing',
                                default => 'dash-badge--pending',
                            };
                        @endphp
                        <a href="{{ route('admin.orders.show', $order->id) }}"
                            class="dash-row flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 px-6 py-4 no-underline">
                            <div class="sm:w-[7.5rem] shrink-0">
                                <p class="font-mono text-sm font-medium text-zibra-ink dark:text-white tracking-tight">
                                    {{ $order->order_number }}
                                </p>
                                <p class="text-[11px] text-zibra-ash mt-0.5">
                                    {{ ($order->placed_at ?? $order->created_at)?->format('M d · h:i A') }}
                                </p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zibra-ink dark:text-white truncate">
                                    {{ $order->customerName() }}
                                </p>
                                <p class="text-xs text-zibra-ash truncate mt-0.5">
                                    {{ $order->customerPhone() ?: ($order->customerEmail() ?: 'Guest checkout') }}
                                </p>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-4 sm:w-48 shrink-0 min-w-0">
                                <span class="dash-badge {{ $badgeClass }}">{{ $status }}</span>
                                <span class="box-fit min-w-0 flex-1 sm:flex-none sm:w-[5.5rem] text-right">
                                    <span class="fit-num text-sm font-semibold text-zibra-ink dark:text-white" style="--fit-num-max: 0.875rem">
                                        {{ \App\Support\Money::format($order->total_amount ?? 0) }}
                                    </span>
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <p class="dash-label mb-2">Empty</p>
                            <p class="text-sm text-zibra-ash">No orders yet — they’ll appear here as soon as checkout lands.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Top products + low stock --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="dash-panel dash-reveal dash-reveal-d6 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <span class="dash-section-mark" aria-hidden="true"></span>
                        <p class="dash-label mb-1">Top selling</p>
                        <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Products</h2>
                    </div>
                    <a href="{{ route('admin.products.index') }}" class="dash-link inline-flex items-center gap-2">
                        Catalog <i class="fas fa-arrow-right text-[9px]"></i>
                    </a>
                </div>

                <div>
                    @php
                        $maxSales = max(1, (int) collect($topProducts)->max('sales_count'));
                    @endphp
                    @forelse ($topProducts as $index => $product)
                        @php
                            $name = $product->translated_name
                                ?? (is_array($product->name)
                                    ? ($product->name[app()->getLocale()] ?? $product->name['en'] ?? 'Product')
                                    : ($product->name ?? 'Product'));
                            $imageUrl = $product->photos?->first()?->url;
                            $sales = (int) ($product->sales_count ?? 0);
                            $share = min(100, (int) round(($sales / $maxSales) * 100));
                        @endphp
                        <div class="dash-row px-6 py-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <span class="w-5 shrink-0 text-[11px] font-mono text-zibra-ash tabular-nums">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="" class="dash-thumb shrink-0">
                                @else
                                    <div class="dash-thumb shrink-0 flex items-center justify-center text-xs font-semibold text-zibra-ash bg-zibra-paper dark:bg-gray-900">
                                        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-zibra-ink dark:text-white truncate">{{ $name }}</p>
                                    <p class="text-xs text-zibra-ash mt-0.5">{{ $sales }} {{ Str::plural('sale', $sales) }}</p>
                                    <div class="dash-meter mt-2.5">
                                        <span style="width: {{ $share }}%; animation-delay: {{ 0.15 + $index * 0.08 }}s"></span>
                                    </div>
                                </div>
                                <p class="box-fit w-[5.5rem] text-right">
                                    <span class="fit-num text-sm font-semibold text-zibra-ink dark:text-white" style="--fit-num-max: 0.875rem">
                                        {{ \App\Support\Money::format($product->revenue ?? 0) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-14 text-center text-sm text-zibra-ash">No product sales yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="dash-panel dash-reveal dash-reveal-d7 p-0 overflow-hidden">
                <div class="px-6 py-5 border-b border-zibra-line dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <span class="dash-section-mark" aria-hidden="true"></span>
                        <p class="dash-label mb-1">Inventory</p>
                        <h2 class="text-lg font-semibold text-zibra-ink dark:text-white tracking-tight">Low stock</h2>
                    </div>
                    <a href="{{ route('admin.inventory.index') }}" class="dash-link inline-flex items-center gap-2">
                        Inventory <i class="fas fa-arrow-right text-[9px]"></i>
                    </a>
                </div>

                <div>
                    @forelse ($lowStockProducts as $product)
                        @php
                            $name = $product->translated_name
                                ?? (is_array($product->name)
                                    ? ($product->name[app()->getLocale()] ?? $product->name['en'] ?? 'Product')
                                    : ($product->name ?? 'Product'));
                            $stock = (int) ($product->stock_left ?? $product->variants->sum('stock'));
                            $imageUrl = $product->photos?->first()?->url;
                        @endphp
                        <div class="dash-row px-6 py-4 flex items-center gap-4">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="" class="dash-thumb shrink-0">
                            @else
                                <div class="dash-thumb shrink-0 flex items-center justify-center text-xs font-semibold text-zibra-ash">
                                    {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zibra-ink dark:text-white truncate">{{ $name }}</p>
                                <p class="text-xs text-zibra-ash mt-0.5 font-mono">{{ $product->sku ?? 'No SKU' }}</p>
                            </div>
                            <span class="dash-badge dash-badge--cancelled tabular-nums">{{ $stock }} left</span>
                        </div>
                    @empty
                        <div class="px-6 py-14 text-center">
                            <p class="text-sm font-medium text-zibra-ink dark:text-white mb-1">Stock looks healthy</p>
                            <p class="text-xs text-zibra-ash">No variants under 10 units right now.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script type="application/json" id="dokannward-dash-chart-data">
        {!! json_encode([
            'currency' => $currencySymbol ?? 'LE',
            'revenueLabels' => $revenueChartLabels,
            'revenueData' => $revenueChartData,
            'ordersLabels' => $ordersChartLabels,
            'ordersData' => $ordersChartData,
            'categoryLabels' => $categoryChartLabels,
            'categoryData' => $categoryChartData,
        ], JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script>
        (function () {
            var chartInstances = [];

            function readData() {
                var el = document.getElementById('dokannward-dash-chart-data');
                if (!el) return null;
                try { return JSON.parse(el.textContent); } catch (e) { return null; }
            }

            function destroyCharts() {
                chartInstances.forEach(function (c) {
                    try { c.destroy(); } catch (e) {}
                });
                chartInstances = [];
            }

            function bootCharts() {
                if (typeof Chart === 'undefined') {
                    window.setTimeout(bootCharts, 40);
                    return;
                }

                var revenueEl = document.getElementById('revenueChart');
                var ordersEl = document.getElementById('ordersChart');
                var categoryEl = document.getElementById('categoryChart');
                if (!revenueEl && !ordersEl && !categoryEl) return;

                var data = readData();
                if (!data) return;

                destroyCharts();

                var ink = '#0a0a0a';
                var ash = '#6b6b6b';
                var mist = '#ececec';
                var graphite = '#2a2a2a';
                var red = '#9e1b2e';
                var redSoft = '#c45a68';
                var isDark = document.documentElement.classList.contains('dark');
                var tick = isDark ? '#9ca3af' : ash;
                var grid = isDark ? 'rgba(75, 85, 99, 0.35)' : 'rgba(220, 220, 220, 0.9)';
                var surface = isDark ? '#1f2937' : '#ffffff';
                var currency = data.currency || 'LE';
                var line = isDark ? redSoft : red;

                Chart.defaults.font.family = '"Merriweather Sans", "Helvetica Neue", Arial, sans-serif';
                Chart.defaults.color = tick;
                Chart.defaults.borderColor = grid;

                if (revenueEl) {
                    var rctx = revenueEl.getContext('2d');
                    var gradient = rctx.createLinearGradient(0, 0, 0, 280);
                    gradient.addColorStop(0, isDark ? 'rgba(196,90,104,0.28)' : 'rgba(158,27,46,0.18)');
                    gradient.addColorStop(1, 'rgba(158,27,46,0)');

                    chartInstances.push(new Chart(rctx, {
                        type: 'line',
                        data: {
                            labels: data.revenueLabels,
                            datasets: [{
                                label: 'Revenue',
                                data: data.revenueData,
                                borderColor: line,
                                backgroundColor: gradient,
                                borderWidth: 2.25,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                                pointHoverBackgroundColor: line,
                                pointHoverBorderColor: '#fff',
                                pointHoverBorderWidth: 2,
                                tension: 0.35,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: ink,
                                    titleColor: '#fff',
                                    bodyColor: 'rgba(255,255,255,0.85)',
                                    borderColor: red,
                                    borderWidth: 1,
                                    padding: 12,
                                    displayColors: false,
                                    callbacks: {
                                        label: function (ctx) {
                                            return currency + ' ' + Number(ctx.parsed.y).toLocaleString(undefined, {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            });
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: tick, maxRotation: 0 },
                                    border: { display: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: grid, drawBorder: false },
                                    border: { display: false },
                                    ticks: {
                                        color: tick,
                                        callback: function (value) {
                                            return currency + ' ' + Number(value).toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    }));
                }

                if (ordersEl) {
                    chartInstances.push(new Chart(ordersEl.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: data.ordersLabels,
                            datasets: [{
                                label: 'Orders',
                                data: data.ordersData,
                                backgroundColor: isDark ? 'rgba(255,255,255,0.78)' : ink,
                                hoverBackgroundColor: line,
                                borderWidth: 0,
                                borderRadius: 0,
                                maxBarThickness: 28
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: ink,
                                    titleColor: '#fff',
                                    bodyColor: 'rgba(255,255,255,0.85)',
                                    borderColor: red,
                                    borderWidth: 1,
                                    padding: 12,
                                    displayColors: false
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: tick, maxRotation: 0 },
                                    border: { display: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { color: tick, precision: 0 },
                                    grid: { color: grid },
                                    border: { display: false }
                                }
                            }
                        }
                    }));
                }

                if (categoryEl) {
                    var palette = isDark
                        ? [redSoft, '#ffffff', '#d1d5db', '#9ca3af', '#6b7280']
                        : [red, ink, graphite, ash, mist];

                    chartInstances.push(new Chart(categoryEl.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.categoryLabels,
                            datasets: [{
                                data: data.categoryData,
                                backgroundColor: palette.slice(0, Math.max((data.categoryLabels || []).length, 1)),
                                borderColor: surface,
                                borderWidth: 3,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        color: tick,
                                        padding: 14,
                                        font: { size: 11 }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: ink,
                                    titleColor: '#fff',
                                    bodyColor: 'rgba(255,255,255,0.85)',
                                    borderColor: red,
                                    borderWidth: 1,
                                    padding: 12,
                                    displayColors: false
                                }
                            }
                        }
                    }));
                }
            }

            document.addEventListener('DOMContentLoaded', bootCharts);
            document.addEventListener('turbo:load', bootCharts);
            document.addEventListener('turbo:before-cache', destroyCharts);
            document.addEventListener('admin:theme-change', bootCharts);
            bootCharts();
        })();
    </script>
@endpush
