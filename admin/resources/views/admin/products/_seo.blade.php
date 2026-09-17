{{--
  Professional SEO editor with live Google SERP preview + character guidance.
  Expects optional $product (edit) or empty (create).
--}}
@php
    $product = $product ?? null;
    $seoTitle = old('meta_title', $product->meta_title ?? '');
    $seoDescription = old('meta_description', $product->meta_description ?? '');
    $seoKeywords = old('meta_keywords', $product->meta_keywords ?? '');
    $seoSlug = $product->slug ?? '';
    $fallbackName = old('name', $product->name['en'] ?? 'Product name');

    // Public storefront origin (never admin APP_URL / a local/dev host).
    $storefront = rtrim((string) config('app.storefront_public_url', config('app.frontend_url')), '/');
    $parts = parse_url($storefront) ?: [];
    $siteHost = $parts['host'] ?? parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: 'dokannward.com';
    $siteOrigin = ($parts['scheme'] ?? 'https').'://'.$siteHost.(isset($parts['port']) ? ':'.$parts['port'] : '');
@endphp

<x-admin.card title="SEO & Metadata" subtitle="How this product appears in Google search results" icon="fas fa-search"
    variant="default">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        {{-- Editor --}}
        <div class="space-y-5" id="seo-editor"
            data-fallback-name="{{ e($fallbackName) }}"
            data-site-origin="{{ e($siteOrigin) }}"
            data-slug="{{ e($seoSlug) }}">

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="meta_title" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Meta Title
                    </label>
                    <span id="meta-title-count"
                        class="text-xs font-mono tabular-nums text-gray-400">0 / 60</span>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-heading text-gray-400"></i>
                    </div>
                    <input type="text" id="meta_title" name="meta_title" maxlength="70"
                        value="{{ $seoTitle }}"
                        placeholder="Leave empty to use the product name"
                        class="w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 pl-10 px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400">
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div id="meta-title-bar" class="h-full bg-emerald-500 transition-all duration-200" style="width: 0%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Aim for 50–60 characters for best display.</p>
                @error('meta_title')
                    <p class="mt-1 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="meta_description" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Meta Description
                    </label>
                    <span id="meta-desc-count"
                        class="text-xs font-mono tabular-nums text-gray-400">0 / 160</span>
                </div>
                <textarea id="meta_description" name="meta_description" rows="4" maxlength="180"
                    placeholder="A clear, compelling summary that encourages clicks from search results…"
                    class="w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400 resize-y">{{ $seoDescription }}</textarea>
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div id="meta-desc-bar" class="h-full bg-emerald-500 transition-all duration-200" style="width: 0%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Aim for 120–160 characters.</p>
                @error('meta_description')
                    <p class="mt-1 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="meta_keywords" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Focus Keywords
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-tags text-gray-400"></i>
                    </div>
                    <input type="text" id="meta_keywords" name="meta_keywords" maxlength="255"
                        value="{{ $seoKeywords }}"
                        placeholder="leather bag, tote, travel duffle"
                        class="w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 pl-10 px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400">
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Comma-separated. Optional — used for internal tagging.</p>
                @error('meta_keywords')
                    <p class="mt-1 text-sm text-red-600"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Google SERP preview --}}
        <div>
            <p class="text-xs uppercase tracking-[0.14em] text-gray-400 mb-3 font-semibold">Search preview</p>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-[#fff] dark:bg-gray-900 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold text-gray-500">
                        Z
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-800 dark:text-gray-200 leading-tight truncate">{{ $brandDisplayName ?? 'Store' }}</p>
                        <p id="seo-preview-url" class="text-xs text-gray-500 truncate">
                            {{ $siteOrigin }}/products/{{ $seoSlug ?: 'product-slug' }}
                        </p>
                    </div>
                </div>
                <h4 id="seo-preview-title"
                    class="text-[20px] leading-snug text-[#1a0dab] dark:text-[#8ab4f8] font-normal mb-1 line-clamp-2">
                    {{ $seoTitle !== '' ? $seoTitle : $fallbackName }}
                </h4>
                <p id="seo-preview-desc"
                    class="text-sm text-[#4d5156] dark:text-gray-400 leading-relaxed line-clamp-3">
                    {{ $seoDescription !== '' ? $seoDescription : 'Add a meta description to control how this product appears under the title in Google.' }}
                </p>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span id="seo-title-badge"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Title
                </span>
                <span id="seo-desc-badge"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Description
                </span>
            </div>
        </div>
    </div>
</x-admin.card>

@once
    @push('scripts')
        <script>
            (function () {
                const root = document.getElementById('seo-editor');
                if (!root) return;

                const titleInput = document.getElementById('meta_title');
                const descInput = document.getElementById('meta_description');
                const nameInput = document.querySelector('input[name="name"]');
                const previewTitle = document.getElementById('seo-preview-title');
                const previewDesc = document.getElementById('seo-preview-desc');
                const previewUrl = document.getElementById('seo-preview-url');
                const titleCount = document.getElementById('meta-title-count');
                const descCount = document.getElementById('meta-desc-count');
                const titleBar = document.getElementById('meta-title-bar');
                const descBar = document.getElementById('meta-desc-bar');
                const titleBadge = document.getElementById('seo-title-badge');
                const descBadge = document.getElementById('seo-desc-badge');

                const siteOrigin = root.dataset.siteOrigin || @json(rtrim((string) config('app.storefront_public_url', config('app.frontend_url')), '/'));
                const slug = root.dataset.slug || '';

                function tone(len, idealMin, idealMax, hardMax) {
                    if (len === 0) return { color: 'bg-gray-300', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300', label: 'Empty', dot: 'bg-gray-400' };
                    if (len < idealMin) return { color: 'bg-amber-400', badge: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300', label: 'Too short', dot: 'bg-amber-400' };
                    if (len <= idealMax) return { color: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300', label: 'Good', dot: 'bg-emerald-500' };
                    if (len <= hardMax) return { color: 'bg-orange-400', badge: 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300', label: 'Long', dot: 'bg-orange-400' };
                    return { color: 'bg-red-500', badge: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300', label: 'Too long', dot: 'bg-red-500' };
                }

                function setBadge(el, label, t) {
                    el.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ' + t.badge;
                    el.innerHTML = '<span class="w-1.5 h-1.5 rounded-full ' + t.dot + '"></span>' + label + ' · ' + t.label;
                }

                function refresh() {
                    const fallback = (nameInput && nameInput.value.trim()) || root.dataset.fallbackName || 'Product name';
                    const title = titleInput.value.trim() || fallback;
                    const desc = descInput.value.trim() || 'Add a meta description to control how this product appears under the title in Google.';

                    previewTitle.textContent = title;
                    previewDesc.textContent = desc;
                    previewUrl.textContent = siteOrigin.replace(/\/$/, '') + '/products/' + (slug || 'product-slug');

                    const tLen = titleInput.value.length;
                    const dLen = descInput.value.length;
                    titleCount.textContent = tLen + ' / 60';
                    descCount.textContent = dLen + ' / 160';

                    const tt = tone(tLen, 50, 60, 70);
                    const dt = tone(dLen, 120, 160, 180);
                    titleBar.className = 'h-full transition-all duration-200 ' + tt.color;
                    descBar.className = 'h-full transition-all duration-200 ' + dt.color;
                    titleBar.style.width = Math.min(100, (tLen / 60) * 100) + '%';
                    descBar.style.width = Math.min(100, (dLen / 160) * 100) + '%';

                    setBadge(titleBadge, 'Title', tt);
                    setBadge(descBadge, 'Description', dt);
                }

                titleInput.addEventListener('input', refresh);
                descInput.addEventListener('input', refresh);
                if (nameInput) nameInput.addEventListener('input', refresh);
                refresh();
            })();
        </script>
    @endpush
@endonce
