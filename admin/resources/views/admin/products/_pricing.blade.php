@php
    $baseValue = $baseValue ?? old('base_price');
    $saleValue = $saleValue ?? old('sale_price');
    $stockValue = $stockValue ?? old('stock_quantity', 0);
    $stockHelp = $stockHelp ?? null;
    $base = is_numeric($baseValue) ? (float) $baseValue : null;
    $sale = is_numeric($saleValue) ? (float) $saleValue : null;
    $onSale = $base !== null && $sale !== null && $sale > 0 && $sale < $base;
    $savePct = $onSale ? (int) round((($base - $sale) / $base) * 100) : 0;
@endphp

<div class="pricing-studio" data-pricing-studio>
    <p class="pricing-studio__intro">
        Set the original price, then optionally a lower sale price. Shoppers see both —
        the original struck through next to the sale amount.
    </p>

    <div class="pricing-studio__grid">
        <div class="pricing-studio__field">
            <label for="base_price" class="pricing-studio__label">
                Original price <span class="text-red-500">*</span>
            </label>
            <div class="pricing-studio__input-wrap">
                <span class="pricing-studio__currency" aria-hidden="true">LE</span>
                <input
                    type="number"
                    id="base_price"
                    name="base_price"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    placeholder="0.00"
                    required
                    value="{{ $baseValue }}"
                    data-pricing-base
                    class="pricing-studio__input @error('base_price') is-invalid @enderror"
                >
            </div>
            @error('base_price')
                <p class="pricing-studio__error"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
            @else
                <p class="pricing-studio__hint">Price before any discount</p>
            @enderror
        </div>

        <div class="pricing-studio__field">
            <label for="sale_price" class="pricing-studio__label">
                Sale price <span class="pricing-studio__optional">optional</span>
            </label>
            <div class="pricing-studio__input-wrap">
                <span class="pricing-studio__currency" aria-hidden="true">LE</span>
                <input
                    type="number"
                    id="sale_price"
                    name="sale_price"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    placeholder="Leave empty if no sale"
                    value="{{ $saleValue }}"
                    data-pricing-sale
                    class="pricing-studio__input @error('sale_price') is-invalid @enderror"
                >
            </div>
            @error('sale_price')
                <p class="pricing-studio__error"><i class="fas fa-exclamation-circle"></i> {{ $message }}</p>
            @else
                <p class="pricing-studio__hint">Must be lower than the original price</p>
            @enderror
        </div>

        <div class="pricing-studio__field">
            <x-admin.input
                label="Stock Quantity"
                name="stock_quantity"
                type="number"
                placeholder="0"
                icon="fas fa-boxes"
                :required="true"
                :value="$stockValue"
                :error="$errors->first('stock_quantity')"
                :helpText="$stockHelp"
            />
        </div>
    </div>

    <div
        class="pricing-studio__preview{{ $onSale ? ' is-on-sale' : '' }}"
        data-pricing-preview
        aria-live="polite"
    >
        <div class="pricing-studio__preview-copy">
            <span class="pricing-studio__preview-eyebrow" data-pricing-eyebrow>
                {{ $onSale ? 'Customer sees' : 'Storefront price' }}
            </span>
            <div class="pricing-studio__preview-prices">
                <span class="pricing-studio__now" data-pricing-now>
                    LE {{ number_format($onSale ? $sale : ($base ?? 0), 2) }}
                </span>
                <span
                    class="pricing-studio__was{{ $onSale ? '' : ' is-hidden' }}"
                    data-pricing-was
                >
                    LE {{ number_format($base ?? 0, 2) }}
                </span>
                <span
                    class="pricing-studio__badge{{ $onSale ? '' : ' is-hidden' }}"
                    data-pricing-badge
                >
                    −{{ $savePct }}%
                </span>
            </div>
            <p class="pricing-studio__preview-note" data-pricing-note>
                @if ($onSale)
                    Saving LE {{ number_format($base - $sale, 2) }}
                @else
                    Add a sale price below the original to show a discount
                @endif
            </p>
        </div>
    </div>
</div>
