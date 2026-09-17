{{-- Same Dokan Ward brand loader used on the public storefront --}}
<div class="brand-loader{{ !empty($compact) ? ' brand-loader--compact' : '' }}" role="status" aria-live="polite" aria-busy="true">
    <div class="brand-loader__stage" aria-hidden="true">
        <span class="brand-loader__glow"></span>
        <span class="brand-loader__ring"></span>
        <span class="brand-loader__ring brand-loader__ring--delayed"></span>
        <div class="brand-loader__logo-wrap">
            <img
                src="{{ ($brandLogoUrl ?? asset('images/brand-logo.png')) }}"
                alt="{{ $brandDisplayName ?? 'Store' }}"
                width="220"
                height="56"
                class="brand-loader__logo"
                fetchpriority="high"
                decoding="sync"
            >
            <span class="brand-loader__shine"></span>
        </div>
    </div>

    <div class="brand-loader__stripes" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>

    <span class="sr-only">{{ $label ?? 'Loading' }}</span>
</div>
