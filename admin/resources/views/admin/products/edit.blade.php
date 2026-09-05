@extends('admin.layouts.app')

@section('title', 'Edit Product')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="Edit Product"
            subtitle="Update product information"
            back-url="{{ route('admin.products.index') }}"
        />

        <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data"
            class="admin-page-stack" data-turbo="false" data-product-save-form
            data-success-url="{{ route('admin.products.index') }}"
            data-product-id="{{ $product->id }}">
            @csrf
            @method('PUT')

            <!-- Basic Information -->
            <x-admin.card title="Basic Information" icon="fas fa-info-circle" variant="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-admin.input label="Product Name" name="name" type="text"
                            placeholder="Enter product name" icon="fas fa-tag" :required="true" :value="old('name', $product->name['en'] ?? '')"
                            :error="$errors->first('name')" />
                    </div>

                    <x-admin.input label="SKU" name="sku" type="text"
                        icon="fas fa-barcode" :value="old('sku', $product->sku)"
                        helpText="Auto-generated · unique · locked" disabled
                        class="opacity-70 cursor-not-allowed" />

                    <x-admin.input label="Slug" name="slug" type="text"
                        icon="fas fa-link" :value="old('slug', $product->slug)"
                        helpText="Auto-generated · unique · locked" disabled
                        class="opacity-70 cursor-not-allowed" />

                    <x-admin.textarea label="Short Description (English)" name="short_description_en"
                        placeholder="Enter brief product description in English..." :rows="2"
                        :error="$errors->first('short_description_en')">{{ old('short_description_en', $product->short_description['en'] ?? '') }}</x-admin.textarea>

                    <x-admin.textarea label="Short Description (Arabic)" name="short_description_ar"
                        placeholder="Enter brief product description in Arabic..." :rows="2"
                        :error="$errors->first('short_description_ar')">{{ old('short_description_ar', $product->short_description['ar'] ?? '') }}</x-admin.textarea>

                    <x-admin.textarea label="Description (English)" name="description_en"
                        placeholder="Enter detailed product description in English..." :rows="4"
                        :error="$errors->first('description_en')">{{ old('description_en', $product->description['en'] ?? '') }}</x-admin.textarea>

                    <x-admin.textarea label="Description (Arabic)" name="description_ar"
                        placeholder="Enter detailed product description in Arabic..." :rows="4"
                        :error="$errors->first('description_ar')">{{ old('description_ar', $product->description['ar'] ?? '') }}</x-admin.textarea>
                </div>
            </x-admin.card>

            <!-- Categorization -->
            @include('admin.products._categorization', [
                'selectedBrandId' => old('brand_id', $product->brand_id),
            ])

            <!-- Pricing & Stock -->
            <x-admin.card title="Pricing & Inventory" icon="fas fa-dollar-sign" variant="default">
                @php
                    $variantStock = $product->variants->whereNull('color_id')->first()?->stock ?? $product->variants->sum('stock');
                    $editBase = $product->price_max ?? $product->price;
                    $editSale = ($product->price_min != null && $product->price_max != null && (float) $product->price_min < (float) $product->price_max)
                        ? $product->price_min
                        : null;
                @endphp
                @include('admin.products._pricing', [
                    'baseValue' => old('base_price', $editBase),
                    'saleValue' => old('sale_price', $editSale),
                    'stockValue' => old('stock_quantity', $variantStock),
                    'stockHelp' => 'Only used when this product has no color variants below',
                ])
            </x-admin.card>

            <!-- Product Details -->
            <x-admin.card title="Product Details" icon="fas fa-list" variant="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.input label="Material" name="material" type="text"
                        placeholder="e.g., Leather, Canvas, Nylon" icon="fas fa-scroll" :value="old('material', $product->material)"
                        :error="$errors->first('material')" />

                    <x-admin.input label="Weight" name="weight" type="text" placeholder="e.g., 500g"
                        icon="fas fa-weight" :value="old('weight', $product->weight_kg)" :error="$errors->first('weight')" />
                </div>
            </x-admin.card>

            <!-- Sizes -->
            <x-admin.card title="Sizes" icon="fas fa-ruler-combined" variant="default">
                @php
                    $selectedSizeIds = old('size_ids', $product->sizes->pluck('id')->all());
                @endphp
                @include('admin.products._sizes-picker', ['sizes' => $sizes, 'selectedSizeIds' => $selectedSizeIds])
            </x-admin.card>

            <!-- Color Variants -->
            <x-admin.card title="Color Variants" icon="fas fa-palette" variant="default">
                <p class="color-variants-intro">
                    Each color has its own photos. When a customer picks a swatch on the product page,
                    that color’s images open as the main product gallery. The <strong>Primary</strong>
                    color is preselected for customers and used when they add without changing color.
                    If sizes are selected above, each color gets a stock field per size instead of one.
                </p>

                @include('admin.products._color-photos-script')

                @php
                    $hasSizesInitially = count($selectedSizeIds) > 0;
                    $colorGroups = $product->variants->whereNotNull('color_id')->groupBy('color_id');
                    $savedDefaultColorId = $product->variants->whereNotNull('color_id')->firstWhere('is_default', true)?->color_id
                        ?? $colorGroups->keys()->first();
                    $defaultSelection = old('default_color', $savedDefaultColorId ? 'existing:'.$savedDefaultColorId : null);
                @endphp

                @if ($colorGroups->isNotEmpty())
                    <div class="color-variants-list mb-4">
                        @foreach ($colorGroups as $colorId => $variants)
                            @php
                                $color = $variants->first()->color;
                                // Every size variant of a color shares the same photo set.
                                // Pivot position decides which photo leads the color on the storefront.
                                $colorPhotos = $variants
                                    ->flatMap(fn ($v) => $v->photos)
                                    ->unique('id')
                                    ->sortBy(fn ($p) => $p->pivot->position ?? PHP_INT_MAX)
                                    ->values();
                                $sizedVariants = $variants->whereNotNull('size_id')->sortBy(fn ($v) => $v->size->sort_order ?? 0);
                                $plainVariant = $variants->firstWhere('size_id', null) ?? $variants->first();
                            @endphp
                            <div
                                data-color-row
                                data-stock-prefix-plain="existing_variants[{{ $plainVariant->id }}]"
                                data-stock-prefix-sized="color_groups[{{ $colorId }}]"
                                class="color-variant-row{{ $defaultSelection === 'existing:'.$colorId ? ' color-variant-row--primary' : '' }}"
                            >
                                <div class="color-variant-row__toolbar">
                                    <label class="color-variant-row__primary" data-primary-label>
                                        <input type="radio" name="default_color" value="existing:{{ $colorId }}"
                                            @checked($defaultSelection === 'existing:'.$colorId)
                                            class="text-amber-500 focus:ring-amber-500">
                                        <span class="color-variant-row__primary-icon" aria-hidden="true"><i class="fas fa-star"></i></span>
                                        <span class="color-variant-row__primary-text">Primary</span>
                                    </label>

                                    @php
                                        $editColorName = old("color_groups.$colorId.name", $color->name ?? '');
                                        $editColorHex = old("color_groups.$colorId.hex", $color->hex ?? '#000000');
                                        if (! is_string($editColorHex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $editColorHex)) {
                                            $editColorHex = '#000000';
                                        }
                                    @endphp

                                    <div class="color-variant-row__fields">
                                        <div class="color-variant-row__field color-variant-row__field--name">
                                            <label class="color-variant-row__label">Color name</label>
                                            <input type="text" name="color_groups[{{ $colorId }}][name]"
                                                value="{{ $editColorName }}" list="color-presets"
                                                placeholder="e.g. Black">
                                        </div>
                                        <div class="color-variant-row__field color-variant-row__field--swatch">
                                            <label class="color-variant-row__label">Swatch</label>
                                            <input type="color" name="color_groups[{{ $colorId }}][hex]"
                                                value="{{ $editColorHex }}">
                                        </div>
                                        <div class="color-variant-row__field color-variant-row__field--stock" data-stock-slot>
                                            @if ($hasSizesInitially)
                                                <label class="color-variant-row__label">Stock per size</label>
                                                <div class="color-variant-row__stock-grid">
                                                    @foreach ($sizes->whereIn('id', $selectedSizeIds) as $size)
                                                        @php
                                                            $sizeVariant = $sizedVariants->firstWhere('size_id', $size->id);
                                                            $sizeValue = old("color_groups.$colorId.size_stocks.$size->id", $sizeVariant->stock ?? 0);
                                                        @endphp
                                                        <div class="color-variant-row__stock-cell">
                                                            <span class="color-variant-row__stock-cell-label">{{ $size->name }}</span>
                                                            <input type="number" min="0"
                                                                name="color_groups[{{ $colorId }}][size_stocks][{{ $size->id }}]"
                                                                value="{{ $sizeValue }}">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <label class="color-variant-row__label">Stock</label>
                                                <input type="number" min="0" class="color-variant-row__stock-single"
                                                    name="existing_variants[{{ $plainVariant->id }}][stock]"
                                                    value="{{ old('existing_variants.'.$plainVariant->id.'.stock', $plainVariant->stock) }}">
                                            @endif
                                        </div>
                                    </div>

                                    <div class="color-variant-row__actions">
                                        <label class="color-variant-row__archive">
                                            <input type="checkbox" name="remove_colors[]" value="{{ $colorId }}"
                                                data-remove-color
                                                @checked(in_array($colorId, old('remove_colors', []), true))
                                                class="rounded border-gray-300 text-red-500 focus:ring-red-500">
                                            <span>Remove</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="color-variant-row__media">
                                    @include('admin.products._color-photos', [
                                        'inputKey' => $colorId,
                                        'existingPhotos' => $colorPhotos,
                                        'productId' => $product->id,
                                    ])
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div id="color-rows" class="color-variants-list"></div>

                <button type="button" id="add-color-row" class="color-variants-add">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add color
                </button>
            </x-admin.card>

            <!-- Existing Images -->
            @php
                $variantPhotoIds = $product->variants
                    ->flatMap(fn ($v) => $v->photos->pluck('id'))
                    ->unique()
                    ->all();
                $variantPhotoIdSet = array_fill_keys($variantPhotoIds, true);
                // Show every product plate (gallery + color) so any image can be Main.
                $allCoverPhotos = $product->photos
                    ->sortBy(fn ($photo) => [
                        $photo->is_primary ? 0 : 1,
                        $photo->position ?? PHP_INT_MAX,
                    ])
                    ->values();
                $colorNameByPhotoId = [];
                foreach ($product->variants->whereNotNull('color_id') as $variant) {
                    $colorName = $variant->color?->name;
                    if (! $colorName) {
                        continue;
                    }
                    foreach ($variant->photos as $variantPhoto) {
                        $colorNameByPhotoId[$variantPhoto->id] ??= $colorName;
                    }
                }
            @endphp
            @if ($allCoverPhotos->count() > 0)
                @php
                    $mainPhotoId = optional(
                        $allCoverPhotos->firstWhere('is_primary', true) ?? $allCoverPhotos->first()
                    )->id;
                @endphp
                <x-admin.card title="Current Images" icon="fas fa-images" variant="default">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Click <strong>Set as Main</strong> on any photo — from a color or from the gallery —
                        to make it the website cover (cards and default product page).
                    </p>
                    <div class="product-gallery-grid" data-saved-product-gallery role="list" aria-label="Saved product images">
                        @foreach ($allCoverPhotos as $photo)
                            @php
                                $isMain = $photo->id === $mainPhotoId;
                                $isColorPhoto = isset($variantPhotoIdSet[$photo->id]);
                                $colorLabel = $colorNameByPhotoId[$photo->id] ?? null;
                            @endphp
                            <div
                                class="product-gallery-tile{{ $isMain ? ' is-main' : '' }}"
                                role="listitem"
                                data-photo-id="{{ $photo->id }}"
                                data-primary-url="{{ route('admin.products.images.primary', $photo->id) }}"
                            >
                                <img src="{{ $photo->url }}" alt="{{ $photo->alt_text }}" loading="lazy">
                                @if ($isMain)
                                    <span class="product-gallery-tile__badge" title="Shows on the website as the product cover">
                                        <i class="fas fa-star"></i> Main
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        class="product-gallery-tile__make-main is-always-on"
                                        data-set-primary="{{ route('admin.products.images.primary', $photo->id) }}"
                                        title="Use as website cover image"
                                    >Set as Main</button>
                                @endif
                                @if (! $isColorPhoto)
                                    <button type="button"
                                        data-delete-product-image="{{ $photo->id }}"
                                        class="product-gallery-tile__remove" title="Delete" aria-label="Delete image">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                                <span class="product-gallery-tile__caption">
                                    @if ($isMain)
                                        Website cover
                                    @elseif ($colorLabel)
                                        Color · {{ $colorLabel }}
                                    @else
                                        {{ $photo->file_name ?: 'Gallery image' }}
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            @endif

            <!-- Add more images -->
            <x-admin.card title="Add Images" icon="fas fa-cloud-arrow-up" variant="default">
                @include('admin.products._product-images-upload', [
                    'galleryLabel' => 'New images',
                    'galleryHelp' => 'New uploads are appended after current images. To change the website cover, use “Set as Main” on any saved image above (color or gallery).',
                ])
            </x-admin.card>

            <x-admin.card title="Dokan Ward Product Code" icon="fas fa-qrcode" variant="default">
                @include('admin.products._product-qr', ['product' => $product])
            </x-admin.card>

            @include('admin.products._seo', ['product' => $product])

            <!-- Status -->
            <x-admin.card title="Product Status" icon="fas fa-toggle-on" variant="default">
                <div class="space-y-4">
                    <x-admin.toggle label="Active" name="is_active" :checked="old('is_active', $product->status === 'active')"
                        helpText="Make this product visible in the store" />

                    <x-admin.toggle label="Featured" name="is_featured" :checked="old('is_featured', $product->featured)"
                        helpText="Show this product in featured sections" />
                </div>
            </x-admin.card>

            <!-- Actions -->
            <x-admin.form-actions>
                <x-admin.button type="button" variant="outline"
                    onclick="window.location='{{ route('admin.products.index') }}'">
                    Cancel
                </x-admin.button>

                <x-admin.button type="submit" variant="gradient" icon="fas fa-save">
                    Update Product
                </x-admin.button>
            </x-admin.form-actions>
        </form>
    </div>
@endsection

@push('scripts')
    @include('admin.products._pricing-script')
    <script>
        // Color variants repeater — see create.blade.php for the matching
        // server-side handling (colors[i][name|hex], color_images[i]).
        // Existing rows carry both a plain and sized stock field name
        // (existing_variants[id] / color_groups[colorId]) since they were
        // saved before whichever mode the admin is switching into now.
        (function () {
            const container = document.getElementById('color-rows');
            const addBtn = document.getElementById('add-color-row');
            const sizeChips = document.getElementById('size-chips');
            const oldRows = @json(old('colors', []));
            const oldDefault = @json(old('default_color', $defaultSelection));
            const presets = [
                ['Black', '#111111'], ['White', '#f5f5f0'], ['Beige', '#e3d5b8'],
                ['Camel', '#c19a6b'], ['Brown', '#6b4226'], ['Tan', '#d2b48c'],
                ['Red', '#a6192e'], ['Navy', '#1f2a44'], ['Gold', '#c9a227'], ['Grey', '#8c8c8c'],
            ];
            let index = 0;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('"', '&quot;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;');
            }

            function currentSizes() {
                return [...(sizeChips?.querySelectorAll('input[type="checkbox"]:checked') ?? [])]
                    .map((input) => ({ id: input.value, name: input.dataset.sizeName ?? '' }));
            }

            function refreshSizeChipStyles() {
                sizeChips?.querySelectorAll('.size-chip').forEach((chip) => {
                    const checked = chip.querySelector('input').checked;
                    chip.classList.toggle('bg-primary/10', checked);
                    chip.classList.toggle('border-primary', checked);
                    chip.classList.toggle('text-primary', checked);
                });
            }

            function refreshSizesPreview() {
                const preview = document.getElementById('sizes-preview');
                if (!preview) return;
                const sizes = currentSizes();
                preview.textContent = sizes.length
                    ? 'Sizes available ' + sizes.map((s) => s.name).join(' / ')
                    : 'No sizes selected — stock is tracked per color only.';
            }

            // Renders either a single Stock field or a compact grid of one
            // stock input per selected size, keeping whatever values were
            // already entered for sizes that stay selected. `prefixes` is
            // { plain, sized } — new rows use the same `colors[i]` prefix for
            // both; existing rows use their own saved field names.
            function stockFieldHtml(prefixes, plainValue, sizeStocks) {
                const sizes = currentSizes();
                if (!sizes.length) {
                    return `
                        <label class="color-variant-row__label">Stock</label>
                        <input type="number" min="0" name="${prefixes.plain}[stock]" value="${escapeHtml(plainValue ?? 0)}"
                            class="color-variant-row__stock-single" data-stock-field>`;
                }

                const cells = sizes.map((size) => `
                    <div class="color-variant-row__stock-cell">
                        <span class="color-variant-row__stock-cell-label">${escapeHtml(size.name)}</span>
                        <input type="number" min="0" name="${prefixes.sized}[size_stocks][${size.id}]"
                            value="${escapeHtml((sizeStocks && sizeStocks[size.id]) ?? 0)}">
                    </div>`).join('');

                return `
                    <label class="color-variant-row__label">Stock per size</label>
                    <div class="color-variant-row__stock-grid" data-stock-field>${cells}</div>`;
            }

            // Reads whatever is currently in a row's stock slot so re-rendering
            // it (e.g. after toggling a size chip) doesn't lose entered values.
            function readStockValues(row) {
                const sizeInputs = row.querySelectorAll('[data-stock-slot] input[name*="[size_stocks]"]');
                if (sizeInputs.length) {
                    const sizeStocks = {};
                    sizeInputs.forEach((input) => {
                        const match = input.name.match(/\[size_stocks\]\[(.+)\]$/);
                        if (match) sizeStocks[match[1]] = input.value;
                    });
                    return { plain: null, sizeStocks };
                }
                const plainInput = row.querySelector('[data-stock-slot] input[name$="[stock]"]');
                return { plain: plainInput ? plainInput.value : null, sizeStocks: {} };
            }

            function rerenderStockSlots() {
                document.querySelectorAll('[data-color-row]').forEach((row) => {
                    const slot = row.querySelector('[data-stock-slot]');
                    if (!slot) return;
                    const prev = readStockValues(row);
                    const prefixes = { plain: row.dataset.stockPrefixPlain, sized: row.dataset.stockPrefixSized };
                    slot.innerHTML = stockFieldHtml(prefixes, prev.plain, prev.sizeStocks);
                });
            }

            function refreshPrimaryRows() {
                const rows = [...document.querySelectorAll('[data-color-row]')];

                rows.forEach((row) => {
                    const remove = row.querySelector('[data-remove-color]');
                    const radio = row.querySelector('input[name="default_color"]');
                    if (remove) radio.disabled = remove.checked;
                });

                let checked = document.querySelector('input[name="default_color"]:checked:not(:disabled)');
                if (!checked) {
                    const firstAvailable = document.querySelector('input[name="default_color"]:not(:disabled)');
                    if (firstAvailable) {
                        firstAvailable.checked = true;
                        checked = firstAvailable;
                    }
                }

                rows.forEach((row) => {
                    const radio = row.querySelector('input[name="default_color"]');
                    const remove = row.querySelector('[data-remove-color]');
                    const active = radio.checked && !radio.disabled;
                    row.classList.toggle('color-variant-row--primary', active);
                    row.classList.toggle('color-variant-row--muted', Boolean(remove?.checked));
                });
            }

            function addRow(name = '', hex = '#111111', stock = 5, selected = false, sizeStocks = {}) {
                const i = index++;
                const row = document.createElement('div');
                row.dataset.colorRow = String(i);
                row.dataset.stockPrefixPlain = `colors[${i}]`;
                row.dataset.stockPrefixSized = `colors[${i}]`;
                row.className = 'color-variant-row';
                row.innerHTML = `
                    <div class="color-variant-row__toolbar">
                        <label class="color-variant-row__primary" data-primary-label>
                            <input type="radio" name="default_color" value="new:${i}" ${selected ? 'checked' : ''}
                                class="text-amber-500 focus:ring-amber-500">
                            <span class="color-variant-row__primary-icon" aria-hidden="true"><i class="fas fa-star"></i></span>
                            <span class="color-variant-row__primary-text">Primary</span>
                        </label>
                        <div class="color-variant-row__fields">
                            <div class="color-variant-row__field color-variant-row__field--name">
                                <label class="color-variant-row__label">Color name</label>
                                <input type="text" name="colors[${i}][name]" value="${escapeHtml(name)}" list="color-presets" placeholder="e.g. Black">
                            </div>
                            <div class="color-variant-row__field color-variant-row__field--swatch">
                                <label class="color-variant-row__label">Swatch</label>
                                <input type="color" name="colors[${i}][hex]" value="${escapeHtml(hex)}">
                            </div>
                            <div class="color-variant-row__field color-variant-row__field--stock" data-stock-slot></div>
                        </div>
                        <div class="color-variant-row__actions">
                            <button type="button" class="color-variant-row__delete remove-color-row" title="Remove color" aria-label="Remove color">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="color-variant-row__media">${(typeof window.colorPhotosHtml === 'function' ? window.colorPhotosHtml(i) : '')}</div>
                `;
                row.querySelector('[data-stock-slot]').innerHTML = stockFieldHtml(
                    { plain: `colors[${i}]`, sized: `colors[${i}]` }, stock, sizeStocks,
                );
                row.querySelector('input[name="default_color"]').addEventListener('change', refreshPrimaryRows);
                row.querySelector('.remove-color-row').addEventListener('click', () => {
                    row.remove();
                    refreshPrimaryRows();
                });
                container.appendChild(row);
                refreshPrimaryRows();
            }

            if (!document.getElementById('color-presets')) {
                const datalist = document.createElement('datalist');
                datalist.id = 'color-presets';
                datalist.innerHTML = presets.map(([n]) => `<option value="${n}">`).join('');
                document.body.appendChild(datalist);
            }

            sizeChips?.addEventListener('change', () => {
                refreshSizeChipStyles();
                refreshSizesPreview();
                rerenderStockSlots();
            });
            refreshSizeChipStyles();

            document.querySelectorAll('input[name="default_color"]').forEach((radio) => {
                radio.addEventListener('change', refreshPrimaryRows);
            });
            document.querySelectorAll('[data-remove-color]').forEach((checkbox) => {
                checkbox.addEventListener('change', refreshPrimaryRows);
            });

            addBtn.addEventListener('click', () => addRow());

            function bootColorRows() {
                if (typeof window.colorPhotosHtml !== 'function') {
                    window.setTimeout(bootColorRows, 40);
                    return;
                }
                Object.entries(oldRows).forEach(([originalIndex, row]) => addRow(
                    row.name ?? '',
                    row.hex ?? '#111111',
                    row.stock ?? 5,
                    oldDefault === `new:${originalIndex}`,
                    row.size_stocks ?? {},
                ));
                refreshPrimaryRows();

                document.querySelector('[data-product-save-form]')?.addEventListener('dokannward:draft-restored', () => {
                    refreshSizeChipStyles();
                    refreshSizesPreview();
                    rerenderStockSlots();
                    refreshPrimaryRows();
                });
            }
            bootColorRows();
        })();
    </script>
@endpush
