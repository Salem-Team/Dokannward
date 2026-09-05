@extends('admin.layouts.app')

@section('title', 'Settings')

@php
    $g = $settings['general'];
    $c = $settings['currency'];
    $s = $settings['shipping'];
    $i = $settings['inventory'];
    $t = $settings['tax'];
    $p = $settings['payments'];
    $paymentMethods = [
        'cash' => ['name' => 'Cash on delivery', 'icon' => 'fa-money-bill-wave'],
        'instapay' => ['name' => 'InstaPay', 'icon' => 'fa-bolt'],
        'visa' => ['name' => 'Visa / Mastercard', 'icon' => 'fa-credit-card'],
        'wallet' => ['name' => 'Mobile wallet', 'icon' => 'fa-mobile-screen-button'],
    ];
@endphp

@section('content')
    <div class="brand-studio-page settings-studio space-y-6" data-settings-root
        data-settings-url="{{ route('admin.settings.update') }}">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Store</p>
                <h1 class="brand-studio-page__title">Settings</h1>
                <p class="brand-studio-page__sub">
                    Brand, currency, shipping and tax — changes save automatically and refresh the website.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="settings-studio__status" data-settings-status data-state="idle" aria-live="polite">
                    <span class="settings-studio__status-dot" aria-hidden="true"></span>
                    <span data-settings-status-label>Ready</span>
                </div>
                @if (!empty($storefrontBase))
                    <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper transition-colors">
                        View website <i class="fas fa-external-link-alt text-xs opacity-60"></i>
                    </a>
                @endif
            </div>
        </div>

        <p class="settings-studio__banner" data-settings-error hidden></p>

        {{-- Currency --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="currency"
            data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="currency">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Commerce</p>
                    <h2 class="brand-studio__section-title">Currency</h2>
                    <p class="brand-studio__hint">How every price appears on the storefront, cart and checkout.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="brand-studio__label" for="code">Currency code</label>
                        <input id="code" name="code" type="text" required maxlength="10"
                            value="{{ old('code', $c['code']) }}"
                            class="brand-studio__input" placeholder="EGP" autocomplete="off">
                        <p class="brand-studio__hint mt-2">ISO code — EGP, USD, SAR</p>
                    </div>
                    <div>
                        <label class="brand-studio__label" for="symbol">Symbol</label>
                        <input id="symbol" name="symbol" type="text" required maxlength="10"
                            value="{{ old('symbol', $c['symbol']) }}"
                            class="brand-studio__input" placeholder="LE" autocomplete="off">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="position">Symbol position</label>
                        <select id="position" name="position" required class="brand-studio__input">
                            <option value="before" @selected(old('position', $c['position']) === 'before')>
                                Before amount — LE 1,250.00
                            </option>
                            <option value="after" @selected(old('position', $c['position']) === 'after')>
                                After amount — 1,250.00 LE
                            </option>
                        </select>
                    </div>
                </div>

                <div class="settings-studio__preview">
                    <span class="settings-studio__preview-label">Preview</span>
                    <span id="currency-preview" class="settings-studio__preview-value"></span>
                </div>
            </section>
        </form>

        {{-- General / brand --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="general"
            data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="general">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Identity</p>
                    <h2 class="brand-studio__section-title">Store profile</h2>
                    <p class="brand-studio__hint">Name, inbox and announcement used across the site chrome.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="store_name">Store name</label>
                        <input id="store_name" name="store_name" type="text" required maxlength="255"
                            value="{{ old('store_name', $g['store_name']) }}"
                            class="brand-studio__input" autocomplete="organization">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="store_email">Store email</label>
                        <input id="store_email" name="store_email" type="email" required maxlength="255"
                            value="{{ old('store_email', $g['store_email']) }}"
                            class="brand-studio__input" autocomplete="email">
                        <p class="brand-studio__hint mt-2">Shown on Contact and in the footer</p>
                    </div>
                </div>

                <div>
                    <label class="brand-studio__label" for="store_description">Store description</label>
                    <textarea id="store_description" name="store_description" rows="3" maxlength="1000"
                        class="brand-studio__input brand-studio__textarea"
                        placeholder="Short brand line…">{{ old('store_description', $g['store_description']) }}</textarea>
                </div>

                <div>
                    <label class="brand-studio__label" for="store_announcement">Top bar announcement</label>
                    <input id="store_announcement" name="store_announcement" type="text" maxlength="255"
                        value="{{ old('store_announcement', $g['store_announcement'] ?? '') }}"
                        class="brand-studio__input"
                        placeholder="Shop our latest arrivals!">
                    <p class="brand-studio__hint mt-2">Appears across the top of every storefront page</p>
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Brand assets</p>
                    <h2 class="brand-studio__section-title">Logos &amp; SEO</h2>
                    <p class="brand-studio__hint">Header/footer marks and the default social share card. Paths like <code class="text-xs">/images/…</code> or full URLs.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="store_logo">Header logo (on light)</label>
                        <input id="store_logo" name="store_logo" type="text" maxlength="500"
                            value="{{ old('store_logo', $g['store_logo'] ?? '') }}"
                            class="brand-studio__input" placeholder="/images/dokan-ward-logo.png">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="store_logo_on_dark">Footer logo (on dark)</label>
                        <input id="store_logo_on_dark" name="store_logo_on_dark" type="text" maxlength="500"
                            value="{{ old('store_logo_on_dark', $g['store_logo_on_dark'] ?? '') }}"
                            class="brand-studio__input" placeholder="/images/brand/dokan-ward-logo-invoice-white.png">
                    </div>
                </div>

                <div>
                    <label class="brand-studio__label" for="seo_description">Default meta description</label>
                    <textarea id="seo_description" name="seo_description" rows="3" maxlength="320"
                        class="brand-studio__input brand-studio__textarea"
                        placeholder="Short SEO description for Google &amp; social…">{{ old('seo_description', $g['seo_description'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="brand-studio__label" for="seo_og_image">Default Open Graph image</label>
                    <input id="seo_og_image" name="seo_og_image" type="text" maxlength="500"
                        value="{{ old('seo_og_image', $g['seo_og_image'] ?? '') }}"
                        class="brand-studio__input" placeholder="/images/og-share.jpg">
                    <p class="brand-studio__hint mt-2">Ideal 1200×630. Used when a page has no specific share image.</p>
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Contact</p>
                    <h2 class="brand-studio__section-title">Phone</h2>
                    <p class="brand-studio__hint">Footer, contact page and about section.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="store_phone">Phone</label>
                        <input id="store_phone" name="store_phone" type="tel" maxlength="50"
                            value="{{ old('store_phone', $g['store_phone']) }}"
                            class="brand-studio__input" placeholder="+2010…" autocomplete="tel">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="store_whatsapp">WhatsApp</label>
                        <input id="store_whatsapp" name="store_whatsapp" type="tel" maxlength="50"
                            value="{{ old('store_whatsapp', $g['store_whatsapp'] ?? $g['store_phone']) }}"
                            class="brand-studio__input" placeholder="+2010…" autocomplete="tel">
                        <p class="brand-studio__hint mt-2">Leave blank to reuse the phone number</p>
                    </div>
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Atelier</p>
                    <h2 class="brand-studio__section-title">Store location</h2>
                    <p class="brand-studio__hint">
                        English and Arabic addresses appear in the footer, Contact and About.
                        Paste a Google Maps link so clients can open the pin in one tap.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="store_address_label">Headline · English</label>
                        <input id="store_address_label" name="store_address_label" type="text" maxlength="120"
                            value="{{ old('store_address_label', $g['store_address_label'] ?? '') }}"
                            class="brand-studio__input" placeholder="Based in New Cairo">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="store_address_label_ar">Headline · Arabic</label>
                        <input id="store_address_label_ar" name="store_address_label_ar" type="text" maxlength="120"
                            value="{{ old('store_address_label_ar', $g['store_address_label_ar'] ?? '') }}"
                            class="brand-studio__input" placeholder="من القاهرة الجديدة" dir="rtl" lang="ar">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="store_address">Address · English</label>
                        <textarea id="store_address" name="store_address" rows="3" maxlength="500"
                            class="brand-studio__input brand-studio__textarea"
                            placeholder="Street, district, city, country">{{ old('store_address', $g['store_address'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="brand-studio__label" for="store_address_ar">Address · Arabic</label>
                        <textarea id="store_address_ar" name="store_address_ar" rows="3" maxlength="500"
                            class="brand-studio__input brand-studio__textarea"
                            placeholder="الشارع، الحي، المدينة، مصر"
                            dir="rtl" lang="ar">{{ old('store_address_ar', $g['store_address_ar'] ?? '') }}</textarea>
                    </div>
                </div>

                <div>
                    <label class="brand-studio__label" for="store_maps_url">Google Maps link</label>
                    <input id="store_maps_url" name="store_maps_url" type="text" maxlength="700"
                        value="{{ old('store_maps_url', $g['store_maps_url'] ?? '') }}"
                        class="brand-studio__input"
                        placeholder="https://maps.app.goo.gl/… or https://www.google.com/maps/…"
                        inputmode="url" autocomplete="off">
                    <p class="brand-studio__hint mt-2">
                        Open Google Maps, drop Dokan Ward’s pin, then Share → Copy link.
                        If left blank, the footer still opens a Maps search from the English address.
                    </p>
                    @php
                        $mapsPreview = old('store_maps_url', $g['store_maps_url'] ?? '');
                    @endphp
                    @if ($mapsPreview)
                        <a href="{{ $mapsPreview }}" target="_blank" rel="noopener"
                            class="brand-studio__hint mt-3 inline-flex items-center gap-2 text-zibra-ink">
                            Preview Google Maps
                            <i class="fas fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Social</p>
                    <h2 class="brand-studio__section-title">Profile links</h2>
                    <p class="brand-studio__hint">Full URLs preferred. Empty fields stay hidden on the site.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="brand-studio__label" for="social_instagram">Instagram</label>
                        <input id="social_instagram" name="social_instagram" type="text" maxlength="500"
                            value="{{ old('social_instagram', $g['social_instagram'] ?? '') }}"
                            class="brand-studio__input" placeholder="https://www.instagram.com/…"
                            inputmode="url" autocomplete="off">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="social_tiktok">TikTok</label>
                        <input id="social_tiktok" name="social_tiktok" type="text" maxlength="500"
                            value="{{ old('social_tiktok', $g['social_tiktok'] ?? '') }}"
                            class="brand-studio__input" placeholder="https://www.tiktok.com/@…"
                            inputmode="url" autocomplete="off">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="social_facebook">Facebook</label>
                        <input id="social_facebook" name="social_facebook" type="text" maxlength="500"
                            value="{{ old('social_facebook', $g['social_facebook'] ?? '') }}"
                            class="brand-studio__input" placeholder="https://www.facebook.com/…"
                            inputmode="url" autocomplete="off">
                    </div>
                </div>
            </section>
        </form>

        {{-- Shipping --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="shipping"
            data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="shipping">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Fulfillment</p>
                    <h2 class="brand-studio__section-title">Shipping</h2>
                    <p class="brand-studio__hint">
                        One fixed shipping price for every order, fulfilled by the company below.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="standard_shipping_fee">Shipping price</label>
                        <input id="standard_shipping_fee" name="standard_shipping_fee" type="number"
                            min="0" step="0.01" required
                            value="{{ old('standard_shipping_fee', $s['standard_shipping_fee']) }}"
                            class="brand-studio__input"
                            inputmode="decimal">
                        <p class="brand-studio__hint mt-2">Added once to every checkout, regardless of products or subtotal.</p>
                    </div>
                    <div>
                        <label class="brand-studio__label" for="shipping_company">Shipping company <span class="normal-case opacity-50">(optional)</span></label>
                        <input id="shipping_company" name="shipping_company" type="text"
                            maxlength="120"
                            value="{{ old('shipping_company', $s['shipping_company']) }}"
                            class="brand-studio__input"
                            placeholder="e.g. Bosta, Aramex, Dokan Ward Delivery"
                            autocomplete="organization">
                        <p class="brand-studio__hint mt-2">If provided, it is shown to shoppers in the cart and checkout.</p>
                    </div>
                </div>

                <div class="settings-studio__live-preview" data-checkout-preview data-currency-symbol="{{ $c['symbol'] }}"
                    data-currency-position="{{ $c['position'] }}">
                    <p class="brand-studio__label mb-1">Live on checkout</p>
                    <p class="brand-studio__hint mb-3">What shoppers see right now for shipping and tax.</p>
                    <ul class="settings-studio__live-preview-list" data-checkout-preview-list>
                        <li data-preview-shipping>—</li>
                        <li data-preview-tax>—</li>
                        <li data-preview-payments class="opacity-70">—</li>
                    </ul>
                </div>
            </section>
        </form>

        {{-- Inventory visibility --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="inventory"
            data-inventory-form data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="inventory">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Catalog</p>
                    <h2 class="brand-studio__section-title">Stock visibility</h2>
                    <p class="brand-studio__hint">
                        Control what shoppers see without changing stock validation or overselling protection.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="settings-studio__toggle">
                        <div>
                            <p class="brand-studio__label mb-0">Show stock status</p>
                            <p class="brand-studio__hint mt-1 mb-0">Show “In stock” on available products.</p>
                        </div>
                        <label class="settings-studio__switch">
                            <input type="checkbox" name="show_stock_status" value="1"
                                data-stock-status @checked((bool) old('show_stock_status', $i['show_stock_status']))>
                            <span class="settings-studio__switch-ui" aria-hidden="true"></span>
                            <span class="sr-only">Show stock status</span>
                        </label>
                    </div>

                    <div class="settings-studio__toggle" data-stock-quantity-wrap>
                        <div>
                            <p class="brand-studio__label mb-0">Show exact quantities</p>
                            <p class="brand-studio__hint mt-1 mb-0">Show the remaining units for products and colors.</p>
                        </div>
                        <label class="settings-studio__switch">
                            <input type="checkbox" name="show_stock_quantity" value="1"
                                data-stock-quantity @checked((bool) old('show_stock_quantity', $i['show_stock_quantity']))>
                            <span class="settings-studio__switch-ui" aria-hidden="true"></span>
                            <span class="sr-only">Show exact stock quantities</span>
                        </label>
                    </div>
                </div>

                <div class="settings-studio__live-preview">
                    <p class="brand-studio__label mb-1">Product page preview</p>
                    <p class="settings-studio__live-preview-list mb-0" data-stock-preview>—</p>
                </div>
            </section>
        </form>

        {{-- Tax --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="tax"
            data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="tax">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Compliance</p>
                    <h2 class="brand-studio__section-title">Tax</h2>
                    <p class="brand-studio__hint">Applied at checkout when enabled.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-end">
                    <div>
                        <label class="brand-studio__label" for="tax_rate">Tax rate (%)</label>
                        <input id="tax_rate" name="tax_rate" type="number" min="0" max="100"
                            step="0.01" required
                            value="{{ old('tax_rate', $t['tax_rate']) }}"
                            class="brand-studio__input">
                    </div>
                    <div class="settings-studio__toggle">
                        <div>
                            <p class="brand-studio__label mb-0">Enable tax</p>
                            <p class="brand-studio__hint mt-1 mb-0">Turn off to charge no tax at checkout</p>
                        </div>
                        <label class="settings-studio__switch">
                            <input type="checkbox" name="tax_enabled" value="1"
                                @checked((bool) old('tax_enabled', $t['tax_enabled']))>
                            <span class="settings-studio__switch-ui" aria-hidden="true"></span>
                            <span class="sr-only">Enable tax</span>
                        </label>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700 space-y-4">
                    <div>
                        <p class="brand-studio__label mb-1">Customer-facing messages</p>
                        <p class="brand-studio__hint">Use <code>{rate}</code> where the configured percentage should appear.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="brand-studio__label" for="tax_enabled_message">When tax is enabled</label>
                            <input id="tax_enabled_message" name="tax_enabled_message" type="text"
                                maxlength="255" required
                                value="{{ old('tax_enabled_message', $t['tax_enabled_message']) }}"
                                class="brand-studio__input">
                        </div>
                        <div>
                            <label class="brand-studio__label" for="tax_disabled_message">When tax is disabled</label>
                            <input id="tax_disabled_message" name="tax_disabled_message" type="text"
                                maxlength="255" required
                                value="{{ old('tax_disabled_message', $t['tax_disabled_message']) }}"
                                class="brand-studio__input">
                        </div>
                    </div>
                </div>
            </section>
        </form>

        {{-- Payments --}}
        <form class="brand-studio brand-studio--full space-y-6" data-settings-form data-section="payments"
            data-turbo="false" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="payments">

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Checkout</p>
                    <h2 class="brand-studio__section-title">Payment methods</h2>
                    <p class="brand-studio__hint">
                        Switch on the methods you accept and pick the one shoppers land on by default.
                        Keep at least one enabled.
                    </p>
                </div>

                <div class="payment-methods">
                    @foreach ($paymentMethods as $key => $meta)
                        @php
                            $isEnabled = (bool) old($key . '_enabled', $p[$key . '_enabled'] ?? false);
                            $isDefault = old('default_method', $p['default_method'] ?? 'cash') === $key;
                        @endphp
                        <article class="payment-method @if ($isEnabled) is-enabled @endif" data-payment-method="{{ $key }}">
                            <header class="payment-method__head">
                                <span class="payment-method__icon" aria-hidden="true">
                                    <i class="fas {{ $meta['icon'] }}"></i>
                                </span>
                                <div class="payment-method__title">
                                    <p class="payment-method__name">{{ $meta['name'] }}</p>
                                    <p class="payment-method__key">{{ $key }}</p>
                                </div>
                                <label class="settings-studio__switch">
                                    <input type="checkbox" name="{{ $key }}_enabled" value="1"
                                        data-payment-toggle @checked($isEnabled)>
                                    <span class="settings-studio__switch-ui" aria-hidden="true"></span>
                                    <span class="sr-only">Accept {{ $meta['name'] }}</span>
                                </label>
                            </header>

                            <div class="payment-method__body">
                                <div>
                                    <label class="brand-studio__label" for="{{ $key }}_label">Label shown to shoppers</label>
                                    <input id="{{ $key }}_label" name="{{ $key }}_label" type="text" maxlength="120"
                                        value="{{ old($key . '_label', $p[$key . '_label'] ?? '') }}"
                                        class="brand-studio__input">
                                </div>
                                <div>
                                    <label class="brand-studio__label" for="{{ $key }}_instructions">Instructions</label>
                                    <textarea id="{{ $key }}_instructions" name="{{ $key }}_instructions" rows="2"
                                        maxlength="500"
                                        class="brand-studio__input">{{ old($key . '_instructions', $p[$key . '_instructions'] ?? '') }}</textarea>
                                </div>
                                <label class="payment-method__default">
                                    <input type="radio" name="default_method" value="{{ $key }}"
                                        data-payment-default @checked($isDefault)
                                        class="text-amber-500 focus:ring-amber-500">
                                    <span><i class="fas fa-star text-amber-500"></i> Default at checkout</span>
                                </label>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </form>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-settings-root]');
            if (!root) return;

            const url = root.getAttribute('data-settings-url');
            const statusEl = root.querySelector('[data-settings-status]');
            const statusLabel = root.querySelector('[data-settings-status-label]');
            const errorBanner = root.querySelector('[data-settings-error]');
            const forms = Array.from(root.querySelectorAll('[data-settings-form]'));

            const codeInput = document.getElementById('code');
            const symbolInput = document.getElementById('symbol');
            const positionSelect = document.getElementById('position');
            const preview = document.getElementById('currency-preview');

            function renderCurrencyPreview() {
                if (!preview || !symbolInput || !positionSelect) return;
                const symbol = symbolInput.value || 'LE';
                const amount = '1,250.00';
                preview.textContent = positionSelect.value === 'after'
                    ? amount + ' ' + symbol
                    : symbol + ' ' + amount;
            }

            [codeInput, symbolInput, positionSelect].forEach((el) => {
                if (el) el.addEventListener('input', renderCurrencyPreview);
            });
            renderCurrencyPreview();

            function csrfToken() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.content : '';
            }

            function setStatus(state, label) {
                if (!statusEl || !statusLabel) return;
                statusEl.setAttribute('data-state', state);
                statusLabel.textContent = label;
            }

            function clearFieldErrors(form) {
                form.querySelectorAll('.brand-studio__input--error').forEach((el) => {
                    el.classList.remove('brand-studio__input--error');
                });
                form.querySelectorAll('[data-field-error]').forEach((el) => el.remove());
            }

            function showFieldErrors(form, errors) {
                Object.entries(errors || {}).forEach(([name, messages]) => {
                    const field = form.querySelector('[name="' + name + '"]');
                    if (!field) return;
                    field.classList.add('brand-studio__input--error');
                    const msg = Array.isArray(messages) ? messages[0] : String(messages);
                    const tip = document.createElement('p');
                    tip.className = 'brand-studio__error';
                    tip.setAttribute('data-field-error', '1');
                    tip.textContent = msg;
                    field.insertAdjacentElement('afterend', tip);
                });
            }

            function setErrorBanner(message) {
                if (!errorBanner) return;
                if (!message) {
                    errorBanner.hidden = true;
                    errorBanner.textContent = '';
                    return;
                }
                errorBanner.hidden = false;
                errorBanner.textContent = message;
            }

            const timers = new WeakMap();
            const saving = new WeakMap();
            const dirty = new WeakMap();
            const previewRoot = root.querySelector('[data-checkout-preview]');
            const previewShipping = root.querySelector('[data-preview-shipping]');
            const previewTax = root.querySelector('[data-preview-tax]');
            const previewPayments = root.querySelector('[data-preview-payments]');

            function money(amount) {
                const symbol = previewRoot?.getAttribute('data-currency-symbol') || 'LE';
                const position = previewRoot?.getAttribute('data-currency-position') || 'before';
                const value = Number(amount || 0).toLocaleString('en-EG', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                return position === 'after' ? value + ' ' + symbol : symbol + ' ' + value;
            }

            function fillTemplate(template, values) {
                return String(template || '').replace(/\{([a-z_]+)\}/gi, (token, key) => (
                    Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : token
                ));
            }

            function renderCheckoutPreview(checkout) {
                if (!checkout || !previewShipping || !previewTax) return;

                const fee = Number(checkout.standard_shipping_fee || 0);
                const rate = Number(checkout.tax_rate || 0);
                const company = String(checkout.shipping_company || '').trim();

                previewShipping.textContent = 'Shipping' + (company ? ' · ' + company : '') + ' · ' + money(fee);

                previewTax.textContent = checkout.tax_enabled
                    ? fillTemplate(
                        checkout.tax_enabled_message || 'Tax {rate}% added to this order',
                        { rate },
                    )
                    : (checkout.tax_disabled_message || 'Prices shown without tax');

                if (previewPayments) {
                    const methods = Array.isArray(checkout.payment_methods) ? checkout.payment_methods : [];
                    previewPayments.textContent = methods.length
                        ? 'Payment: ' + methods.map((m) => (
                            m.key === checkout.default_payment_method ? m.label + ' (default)' : m.label
                        )).join(' · ')
                        : 'Payment: cash on delivery';
                }
            }

            renderCheckoutPreview({
                standard_shipping_fee: {{ (float) $s['standard_shipping_fee'] }},
                shipping_company: @json($s['shipping_company']),
                tax_rate: {{ (float) $t['tax_rate'] }},
                tax_enabled: @json((bool) $t['tax_enabled']),
                tax_enabled_message: @json($t['tax_enabled_message'] ?? ''),
                tax_disabled_message: @json($t['tax_disabled_message'] ?? ''),
                payment_methods: @json(\App\Http\Controllers\Admin\SettingsController::paymentMethods($p)['methods']),
                default_payment_method: @json(\App\Http\Controllers\Admin\SettingsController::paymentMethods($p)['default_method']),
            });

            async function saveForm(form) {
                if (!url) return;
                if (saving.get(form)) {
                    dirty.set(form, true);
                    return;
                }

                saving.set(form, true);
                dirty.set(form, false);
                clearFieldErrors(form);
                setErrorBanner('');
                setStatus('saving', 'Saving…');

                const body = new FormData(form);

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                        credentials: 'same-origin',
                    });

                    let data = null;
                    try { data = await response.json(); } catch (_) {}

                    if (response.status === 422 && data && data.errors) {
                        showFieldErrors(form, data.errors);
                        const first = Object.values(data.errors)[0];
                        const msg = Array.isArray(first) ? first[0] : 'Please fix the highlighted fields.';
                        setErrorBanner(msg);
                        setStatus('error', 'Not saved');
                        window.toast?.show?.(msg, 'error');
                        return;
                    }

                    if (!response.ok) {
                        const msg = (data && (data.message || data.error)) || ('Could not save (' + response.status + ')');
                        setErrorBanner(msg);
                        setStatus('error', 'Not saved');
                        window.toast?.show?.(msg, 'error');
                        return;
                    }

                    if (data && data.checkout) {
                        renderCheckoutPreview(data.checkout);
                    }

                    setStatus('saved', 'Saved · live');
                    window.toast?.show?.(data?.message || 'Saved — live on the website.', 'success');
                    window.setTimeout(() => {
                        if (statusEl.getAttribute('data-state') === 'saved') {
                            setStatus('idle', 'Up to date');
                        }
                    }, 2200);
                } catch (err) {
                    const msg = (err && err.message) || 'Could not save settings.';
                    setErrorBanner(msg);
                    setStatus('error', 'Not saved');
                    window.toast?.show?.(msg, 'error');
                } finally {
                    saving.set(form, false);
                    if (dirty.get(form)) {
                        window.setTimeout(() => saveForm(form), 0);
                    }
                }
            }

            function scheduleSave(form, immediate) {
                const existing = timers.get(form);
                if (existing) window.clearTimeout(existing);
                setStatus('pending', 'Editing…');
                if (immediate) {
                    timers.delete(form);
                    saveForm(form);
                    return;
                }
                timers.set(form, window.setTimeout(() => saveForm(form), 450));
            }

            // Keep the default radio and the enable switches consistent before a
            // save leaves the browser, so the server never has to reject the pair.
            const paymentCards = Array.from(root.querySelectorAll('[data-payment-method]'));

            function paymentToggle(card) {
                return card.querySelector('[data-payment-toggle]');
            }

            function paymentDefault(card) {
                return card.querySelector('[data-payment-default]');
            }

            function syncPaymentCards() {
                paymentCards.forEach((card) => {
                    card.classList.toggle('is-enabled', Boolean(paymentToggle(card)?.checked));
                });

                const checkedDefault = paymentCards.find((card) => paymentDefault(card)?.checked);
                if (checkedDefault && paymentToggle(checkedDefault)?.checked) return;

                const firstEnabled = paymentCards.find((card) => paymentToggle(card)?.checked);
                const fallback = firstEnabled ? paymentDefault(firstEnabled) : null;
                if (fallback) fallback.checked = true;
            }

            paymentCards.forEach((card) => {
                paymentToggle(card)?.addEventListener('change', syncPaymentCards);
                paymentDefault(card)?.addEventListener('change', () => {
                    const toggle = paymentToggle(card);
                    if (toggle && !toggle.checked) toggle.checked = true;
                    syncPaymentCards();
                });
            });
            syncPaymentCards();

            const inventoryForm = root.querySelector('[data-inventory-form]');
            const stockStatus = inventoryForm?.querySelector('[data-stock-status]');
            const stockQuantity = inventoryForm?.querySelector('[data-stock-quantity]');
            const stockQuantityWrap = inventoryForm?.querySelector('[data-stock-quantity-wrap]');
            const stockPreview = inventoryForm?.querySelector('[data-stock-preview]');

            function syncInventoryVisibility() {
                if (!stockStatus || !stockQuantity) return;
                if (!stockStatus.checked) stockQuantity.checked = false;
                stockQuantity.disabled = !stockStatus.checked;
                stockQuantityWrap?.classList.toggle('opacity-50', !stockStatus.checked);

                if (stockPreview) {
                    stockPreview.textContent = !stockStatus.checked
                        ? 'Stock availability is hidden from shoppers.'
                        : stockQuantity.checked
                            ? 'In stock · 8 units available'
                            : 'In stock';
                }
            }

            stockStatus?.addEventListener('change', syncInventoryVisibility);
            stockQuantity?.addEventListener('change', syncInventoryVisibility);
            syncInventoryVisibility();

            forms.forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    scheduleSave(form, true);
                });

                form.addEventListener('input', (event) => {
                    const t = event.target;
                    if (!(t instanceof HTMLElement)) return;
                    if (!t.matches('input, textarea, select')) return;
                    scheduleSave(form, false);
                });

                form.addEventListener('change', (event) => {
                    const t = event.target;
                    if (!(t instanceof HTMLElement)) return;
                    if (!t.matches('input, textarea, select')) return;
                    scheduleSave(form, true);
                });

                form.addEventListener('focusout', (event) => {
                    const t = event.target;
                    if (!(t instanceof HTMLElement)) return;
                    if (!t.matches('input, textarea, select')) return;
                    scheduleSave(form, true);
                });
            });
        })();
    </script>
@endsection
