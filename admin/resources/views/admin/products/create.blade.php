@extends('admin.layouts.app')

@section('title', 'Add New Product')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="Add New Product"
            subtitle="Create a new product in your catalog"
            back-url="{{ route('admin.products.index') }}"
        />

        <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="admin-page-stack" data-turbo="false" data-product-save-form data-success-url="{{ route('admin.products.index') }}">
            @csrf

            <!-- Basic Information -->
            <x-admin.card title="Basic Information" icon="fas fa-info-circle" variant="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-admin.input label="Product Name" name="name" type="text" placeholder="Enter product name"
                            icon="fas fa-tag" :required="true" :value="old('name')" :error="$errors->first('name')" />
                    </div>

                    <x-admin.input label="SKU" name="sku" type="text"
                        icon="fas fa-barcode" :value="$autoSku"
                        helpText="Auto-generated · unique · cannot be edited" disabled
                        class="opacity-70 cursor-not-allowed" />

                    <x-admin.input label="Slug" name="slug" type="text"
                        placeholder="auto from product name" icon="fas fa-link" :value="old('slug')"
                        helpText="Auto-generated from name · unique · cannot be edited" disabled
                        class="opacity-70 cursor-not-allowed" />

                    <x-admin.textarea label="Short Description (English)" name="short_description_en"
                        placeholder="Enter brief product description in English..." :rows="2"
                        :error="$errors->first('short_description_en')">{{ old('short_description_en') }}</x-admin.textarea>

                    <x-admin.textarea label="Short Description (Arabic)" name="short_description_ar"
                        placeholder="Enter brief product description in Arabic..." :rows="2"
                        :error="$errors->first('short_description_ar')">{{ old('short_description_ar') }}</x-admin.textarea>

                    <x-admin.textarea label="Description (English)" name="description_en"
                        placeholder="Enter detailed product description in English..." :rows="4"
                        :error="$errors->first('description_en')">{{ old('description_en') }}</x-admin.textarea>

                    <x-admin.textarea label="Description (Arabic)" name="description_ar"
                        placeholder="Enter detailed product description in Arabic..." :rows="4"
                        :error="$errors->first('description_ar')">{{ old('description_ar') }}</x-admin.textarea>
                </div>
            </x-admin.card>

            <!-- Categorization -->
            @include('admin.products._categorization', [
                'selectedBrandId' => $selectedBrandId ?? old('brand_id'),
            ])

            <!-- Pricing & Stock -->
            <x-admin.card title="Pricing & Inventory" icon="fas fa-dollar-sign" variant="default">
                @include('admin.products._pricing', [
                    'baseValue' => old('base_price'),
                    'saleValue' => old('sale_price'),
                    'stockValue' => old('stock_quantity', 0),
                ])
            </x-admin.card>

            <!-- Product Details -->
            <x-admin.card title="Product Details" icon="fas fa-list" variant="default">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.input label="Material" name="material" type="text"
                        placeholder="e.g., Leather, Canvas, Nylon" icon="fas fa-scroll" :value="old('material')"
                        :error="$errors->first('material')" />

                    <x-admin.input label="Dimensions" name="dimensions" type="text" placeholder="e.g., 30x20x10 cm"
                        icon="fas fa-ruler-combined" :value="old('dimensions')" :error="$errors->first('dimensions')" />

                    <x-admin.input label="Weight" name="weight" type="text" placeholder="e.g., 500g"
                        icon="fas fa-weight" :value="old('weight')" :error="$errors->first('weight')" />
                </div>
            </x-admin.card>

            <!-- Sizes -->
            <x-admin.card title="Sizes" icon="fas fa-ruler-combined" variant="default">
                @include('admin.products._sizes-picker', [
                    'sizes' => $sizes,
                    'selectedSizeIds' => old('size_ids', []),
                ])
            </x-admin.card>

            <!-- Color Variants -->
            <x-admin.card title="Color Variants" icon="fas fa-palette" variant="default">
                <p class="color-variants-intro">
                    Add one row per color this product comes in. Each color becomes its own purchasable
                    variant with its own stock and its own photos — this is what customers see
                    as swatches on the product page. Mark one as <strong>Primary</strong>; it will be
                    preselected for customers and used when they add the product without changing color.
                    If sizes are selected above, each color gets a stock field per size instead of one.
                </p>

                @include('admin.products._color-photos-script')

                <div id="color-rows" class="color-variants-list"></div>

                <button type="button" id="add-color-row" class="color-variants-add">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add color
                </button>
            </x-admin.card>

            <!-- Images -->
            <x-admin.card title="Product Images" icon="fas fa-images" variant="default">
                @include('admin.products._product-images-upload')
            </x-admin.card>

            @include('admin.products._seo')

            <!-- Status -->
            <x-admin.card title="Product Status" icon="fas fa-toggle-on" variant="default">
                <div class="space-y-4">
                    <x-admin.toggle label="Active" name="is_active" :checked="old('is_active', true)"
                        helpText="Make this product visible in the store" />

                    <x-admin.toggle label="Featured" name="is_featured" :checked="old('is_featured', false)"
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
                    Create Product
                </x-admin.button>
            </x-admin.form-actions>
        </form>
    </div>
@endsection

@push('scripts')
    @include('admin.products._pricing-script')
    <script>
        // Color variants repeater — each row posts as colors[i][name],
        // colors[i][hex], one default_color radio, and an optional
        // color_images[i] file matched up by index on the server. Stock posts
        // as colors[i][stock] (no sizes) or colors[i][size_stocks][sizeId]
        // (sizes selected above) — see stockFieldHtml() below.
        (function () {
            const container = document.getElementById('color-rows');
            const addBtn = document.getElementById('add-color-row');
            const sizeChips = document.getElementById('size-chips');
            const oldRows = @json(old('colors', []));
            const oldDefault = @json(old('default_color'));
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
            // { plain, sized } — for brand-new rows both are the same
            // `colors[i]`; existing rows (edit page) use different names.
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
                container.querySelectorAll('[data-color-row]').forEach((row) => {
                    const slot = row.querySelector('[data-stock-slot]');
                    const prev = readStockValues(row);
                    const prefixes = { plain: row.dataset.stockPrefixPlain, sized: row.dataset.stockPrefixSized };
                    slot.innerHTML = stockFieldHtml(prefixes, prev.plain, prev.sizeStocks);
                });
            }

            function refreshPrimaryRows() {
                const rows = [...container.querySelectorAll('[data-color-row]')];
                const checked = container.querySelector('input[name="default_color"]:checked');

                if (!checked && rows.length) {
                    rows[0].querySelector('input[name="default_color"]').checked = true;
                }

                rows.forEach((row) => {
                    const active = row.querySelector('input[name="default_color"]').checked;
                    row.classList.toggle('color-variant-row--primary', active);
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
                                <input type="text" name="colors[${i}][name]" value="${escapeHtml(name)}" list="color-presets"
                                    placeholder="e.g. Black">
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
            refreshSizesPreview();

            addBtn.addEventListener('click', () => addRow());

            function bootColorRows() {
                if (typeof window.colorPhotosHtml !== 'function') {
                    window.setTimeout(bootColorRows, 40);
                    return;
                }
                const oldEntries = Object.entries(oldRows);
                if (oldEntries.length) {
                    oldEntries.forEach(([originalIndex, row], i) => addRow(
                        row.name ?? '',
                        row.hex ?? '#111111',
                        row.stock ?? 5,
                        oldDefault === `new:${originalIndex}` || (!oldDefault && i === 0),
                        row.size_stocks ?? {},
                    ));
                } else {
                    addRow('', '#111111', 5, true);
                }

                document.querySelector('[data-product-save-form]')?.addEventListener('dokannward:draft-restored', () => {
                    refreshSizeChipStyles();
                    refreshSizesPreview();
                    rerenderStockSlots();
                    refreshPrimaryRows();
                });
            }
            bootColorRows();
        })();

        // Auto-preview slug from name (display only — server generates the final unique slug)
        document.querySelector('input[name="name"]').addEventListener('input', function(e) {
            const slugInput = document.querySelector('input[name="slug"]');
            if (slugInput) {
                slugInput.value = e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });
    </script>
@endpush
