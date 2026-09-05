<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 28px 32px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111111;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            background: #ffffff;
        }

        .label {
            font-size: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .mono {
            font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
        }

        .rule {
            height: 1px;
            background: #dcdcdc;
            border: 0;
            margin: 0;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .brand-logo {
            display: block;
            width: 168px;
            height: auto;
        }

        .brand-sub {
            margin-top: 8px;
            font-size: 8px;
            letter-spacing: 2.2px;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .footer-logo {
            display: block;
            width: 110px;
            height: auto;
            margin-bottom: 10px;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-title {
            font-size: 8px;
            letter-spacing: 2.2px;
            text-transform: uppercase;
            color: #6b6b6b;
            margin-bottom: 6px;
        }

        .order-number {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: -0.5px;
            color: #111111;
        }

        .status-pill {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 10px;
            font-size: 8px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            border: 1px solid #111111;
            color: #111111;
        }

        .hero {
            width: 100%;
            border-collapse: collapse;
            margin: 22px 0 8px;
        }

        .hero td {
            vertical-align: top;
        }

        .total-value {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: -0.6px;
        }

        .placed {
            margin-top: 6px;
            color: #6b6b6b;
            font-size: 10px;
        }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            border: 1px solid #dcdcdc;
        }

        .section th,
        .section td {
            vertical-align: top;
            text-align: left;
        }

        .section-half {
            width: 50%;
            padding: 18px 20px;
        }

        .section-half + .section-half {
            border-left: 1px solid #dcdcdc;
        }

        .section-title {
            margin-bottom: 12px;
        }

        .client-name {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 4px;
        }

        .guest-tag {
            font-size: 8px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #6b6b6b;
            margin-bottom: 10px;
        }

        .detail-row {
            margin-top: 6px;
        }

        .detail-label {
            display: inline-block;
            width: 52px;
            font-size: 8px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #6b6b6b;
        }

        .address-line {
            margin: 0 0 3px;
            font-size: 11px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            border: 1px solid #dcdcdc;
        }

        .items thead th {
            padding: 12px 16px;
            border-bottom: 1px solid #dcdcdc;
            background: #fafafa;
            font-size: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #6b6b6b;
            font-weight: normal;
        }

        .items tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #ececec;
            vertical-align: top;
        }

        .items tbody tr:last-child td {
            border-bottom: 0;
        }

        .item-name {
            font-size: 12px;
            font-weight: bold;
            color: #111111;
            margin: 0 0 4px;
        }

        .item-meta {
            font-size: 9px;
            color: #6b6b6b;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .totals-wrap {
            width: 100%;
            margin-top: 0;
            border-collapse: collapse;
        }

        .totals-box {
            width: 42%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-row td {
            padding: 6px 0;
            color: #6b6b6b;
            font-size: 11px;
        }

        .totals-row td:last-child {
            text-align: right;
            color: #111111;
            white-space: nowrap;
        }

        .totals-divider td {
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .totals-divider .line {
            border-top: 1px solid #dcdcdc;
            height: 1px;
        }

        .totals-final td {
            padding-top: 4px;
            font-size: 12px;
            color: #111111;
        }

        .totals-final td:last-child {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: -0.4px;
        }

        .footer {
            margin-top: 36px;
            padding: 16px 18px;
            background: #111111;
            color: #ffffff;
        }

        .footer-copy {
            font-size: 10px;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
        }

        .notes {
            margin-top: 18px;
            border: 1px solid #dcdcdc;
            padding: 14px 16px;
        }

        .notes p {
            margin: 0;
            white-space: pre-wrap;
            color: #111111;
        }
    </style>
</head>
<body>
    @php
        $clientName = $order->customerName();
        $clientPhone = $order->customerPhone();
        $clientEmail = $order->customerEmail();
        $placedAt = $order->placed_at ?? $order->created_at;
        $address = $order->shippingAddress;
        $itemCount = (int) $order->items->sum('qty');

        $logoPath = public_path('images/brand/dokan-ward-logo-invoice.png');
        $logoWhitePath = public_path('images/brand/dokan-ward-logo-invoice-white.png');
        $logoSrc = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;
        $logoWhiteSrc = is_file($logoWhitePath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoWhitePath))
            : null;
    @endphp

    <table class="header">
        <tr>
            <td style="width: 58%; vertical-align: middle;">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Dokan Ward" class="brand-logo" width="168" height="43">
                @else
                    <div style="font-size: 22px; font-weight: bold; letter-spacing: 4px; text-transform: uppercase;">Dokan Ward</div>
                @endif
                <div class="brand-sub">Commerce invoice</div>
            </td>
            <td class="invoice-meta" style="width: 42%; vertical-align: middle;">
                <div class="invoice-title">Invoice</div>
                <div class="order-number mono">{{ $order->order_number }}</div>
                <div class="status-pill">{{ ucfirst($order->status) }}</div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <table class="hero">
        <tr>
            <td style="width: 62%;">
                <div class="label">Order confirmation</div>
                <div class="placed">
                    Placed {{ $placedAt->format('l, M d, Y') }} at {{ $placedAt->format('h:i A') }}
                </div>
            </td>
            <td style="width: 38%; text-align: right;">
                <div class="label" style="margin-bottom: 6px;">Order total</div>
                <div class="total-value">{{ \App\Support\Money::format($order->total_amount) }}</div>
                <div class="placed">{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</div>
            </td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td class="section-half">
                <div class="label section-title">Client</div>
                <p class="client-name">{{ $clientName }}</p>
                @unless ($order->user)
                    <div class="guest-tag">Guest checkout</div>
                @endunless
                @if ($clientPhone)
                    <div class="detail-row">
                        <span class="detail-label">Phone</span>
                        <span>{{ $clientPhone }}</span>
                    </div>
                @endif
                @if ($clientEmail)
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span>{{ $clientEmail }}</span>
                    </div>
                @endif
            </td>
            <td class="section-half">
                <div class="label section-title">Shipping address</div>
                @if ($address)
                    <p class="address-line" style="font-weight: bold; font-size: 12px;">{{ $address->recipient_name }}</p>
                    <p class="address-line">{{ $address->line_1 }}</p>
                    @if ($address->line_2)
                        <p class="address-line">{{ $address->line_2 }}</p>
                    @endif
                    <p class="address-line">
                        {{ $address->city }}@if ($address->state), {{ $address->state }}@endif
                        @if ($address->postal_code)
                            {{ $address->postal_code }}
                        @endif
                    </p>
                    <p class="address-line" style="color: #6b6b6b;">{{ $address->country }}</p>
                @else
                    <p class="address-line" style="color: #6b6b6b;">No shipping address on file.</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 52%;">Item</th>
                <th style="width: 14%;">Qty</th>
                <th style="width: 17%;" class="amount">Unit</th>
                <th style="width: 17%;" class="amount">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        <p class="item-name">{{ $item->name }}</p>
                        <div class="item-meta">
                            @if ($item->variant?->color)
                                {{ $item->variant->color->name }}
                                @if ($item->sku) · @endif
                            @endif
                            @if ($item->sku)
                                <span class="mono">SKU {{ $item->sku }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $item->qty }}</td>
                    <td class="amount">{{ \App\Support\Money::format($item->unit_price) }}</td>
                    <td class="amount" style="font-weight: bold;">{{ \App\Support\Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap" style="margin-top: 18px;">
        <tr>
            <td></td>
            <td style="width: 42%;">
                <table class="totals-box" style="width: 100%;">
                    <tr class="totals-row">
                        <td>Subtotal</td>
                        <td>{{ \App\Support\Money::format($order->subtotal) }}</td>
                    </tr>
                    <tr class="totals-row">
                        <td>Shipping</td>
                        <td>
                            {{ (float) $order->shipping_amount > 0 ? \App\Support\Money::format($order->shipping_amount) : 'Complimentary' }}
                        </td>
                    </tr>
                    @if ((float) $order->tax_amount > 0)
                        <tr class="totals-row">
                            <td>Tax</td>
                            <td>{{ \App\Support\Money::format($order->tax_amount) }}</td>
                        </tr>
                    @endif
                    <tr class="totals-divider">
                        <td colspan="2"><div class="line"></div></td>
                    </tr>
                    <tr class="totals-final">
                        <td><span class="label">Total</span></td>
                        <td>{{ \App\Support\Money::format($order->total_amount) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($order->notes)
        <div class="notes">
            <div class="label section-title">Client notes</div>
            <p>{{ $order->notes }}</p>
        </div>
    @endif

    @if ($order->delivery_tracking_number)
        <div class="notes">
            <div class="label section-title">Tracking</div>
            <p class="mono">{{ $order->delivery_tracking_number }}</p>
            @if ($order->fulfillment_provider)
                <p style="margin-top: 4px; color: #6b6b6b;">{{ $order->fulfillment_provider }}</p>
            @endif
        </div>
    @endif

    <div class="footer">
        @if ($logoWhiteSrc)
            <img src="{{ $logoWhiteSrc }}" alt="Dokan Ward" class="footer-logo" width="110" height="28">
        @endif
        <p class="footer-copy">
            This invoice was issued for order {{ $order->order_number }} through Dokan Ward Commerce.
            Treat every detail with the same care as the brand experience online.
        </p>
    </div>
</body>
</html>
