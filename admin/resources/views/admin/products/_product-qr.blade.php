{{--
  Unique product QR — tenant product code.
  Props: $product (required)
--}}
@php
    $scanUrl = $product->scanUrl();
    $fileBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($product->sku ?: $product->slug ?: 'product')) ?: 'product';
    $cover = method_exists($product, 'coverPhoto')
        ? ($product->coverPhoto() ?? $product->photos->first())
        : ($product->photos->first() ?? null);
@endphp

<div
    class="product-qr"
    data-product-qr
    data-qr-url="{{ $scanUrl }}"
    data-qr-name="{{ $fileBase }}"
    data-qr-sku="{{ $product->sku }}"
    data-qr-title="{{ $product->translated_name }}"
    data-qr-cover="{{ $cover?->url }}"
    data-qr-logo="{{ ($brandLogoUrl ?? asset('images/brand-logo.png')) }}"
>
    <div class="product-qr__stripes" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>

    <div class="product-qr__stage">
        <div class="product-qr__plate">
            <div class="product-qr__plate-glow" aria-hidden="true"></div>
            <div class="product-qr__frame">
                <canvas data-qr-canvas width="280" height="280" aria-label="Product QR code"></canvas>
                <div class="product-qr__seal" data-qr-seal aria-hidden="true">
                    <img
                        class="product-qr__seal-logo"
                        src="{{ ($brandLogoUrl ?? asset('images/brand-logo.png')) }}"
                        alt=""
                        width="28"
                        height="28"
                        decoding="async"
                    >
                </div>
            </div>
            <p class="product-qr__plate-caption">Scan · Authenticated piece</p>
        </div>

        <div class="product-qr__meta">
            <div class="product-qr__brand-row">
                <span class="product-qr__wordmark">{{ $brandDisplayName ?? 'Store' }}</span>
                <span class="product-qr__chip">Atelier code</span>
            </div>

            <h3 class="product-qr__title">{{ $product->translated_name }}</h3>
            <p class="product-qr__hint">
                Each product carries a unique code. Scan to open the full dossier —
                gallery, pricing, stock, colors, and specs — in the {{ $brandDisplayName ?? 'Store' }} experience.
            </p>

            <dl class="product-qr__facts">
                <div>
                    <dt>SKU</dt>
                    <dd>{{ $product->sku ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Handle</dt>
                    <dd>{{ $product->slug ?: '—' }}</dd>
                </div>
            </dl>

            <div class="product-qr__link" title="{{ $scanUrl }}">
                <span class="product-qr__link-label">Scan URL</span>
                <code class="product-qr__link-value">{{ $scanUrl }}</code>
            </div>

            <div class="product-qr__actions">
                <button type="button" class="product-qr__btn product-qr__btn--primary" data-qr-download>
                    <i class="fas fa-download" aria-hidden="true"></i>
                    <span>Download</span>
                </button>
                <button type="button" class="product-qr__btn" data-qr-print>
                    <i class="fas fa-print" aria-hidden="true"></i>
                    <span>Print label</span>
                </button>
                <button type="button" class="product-qr__btn" data-qr-copy>
                    <i class="fas fa-copy" aria-hidden="true"></i>
                    <span data-qr-copy-label>Copy link</span>
                </button>
                <a class="product-qr__btn" href="{{ $scanUrl }}" target="_blank" rel="noopener">
                    <i class="fas fa-eye" aria-hidden="true"></i>
                    <span>Preview</span>
                </a>
            </div>

            <p class="product-qr__status" data-qr-status hidden></p>
        </div>
    </div>
</div>
