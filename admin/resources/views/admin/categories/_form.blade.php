@php
    $isEdit = isset($category);
    $nameEn = old('name_en', $isEdit ? (is_array($category->name) ? ($category->name['en'] ?? '') : $category->name) : '');
    $nameAr = old('name_ar', $isEdit ? (is_array($category->name) ? ($category->name['ar'] ?? '') : '') : '');
    $slugEn = old('slug_en', $isEdit ? (is_array($category->slug) ? ($category->slug['en'] ?? '') : $category->slug) : '');
    $slugAr = old('slug_ar', $isEdit ? (is_array($category->slug) ? ($category->slug['ar'] ?? '') : '') : '');
    $description = old('description', $isEdit ? $category->description : '');
    $position = old('position', $isEdit ? $category->position : 0);
    if (old('is_featured') !== null) {
        $isFeatured = filter_var(old('is_featured'), FILTER_VALIDATE_BOOLEAN);
    } elseif ($isEdit) {
        $isFeatured = (bool) $category->is_featured;
    } else {
        // New categories appear on the homepage by default.
        $isFeatured = true;
    }
    $imageUrl = old('image_url', $isEdit ? $category->path : '');
    $imageSource = old('image_source', 'upload');
    $logoUrl = old('logo_url', $isEdit ? $category->logo_url : '');
    $logoSource = old('logo_source', 'upload');
    $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="brand-studio brand-studio--full" data-turbo="false">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="brand-studio__grid brand-studio__grid--wide">
        {{-- ─── Left: editor ─── --}}
        <div class="brand-studio__editor space-y-8">
            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Identity</p>
                <h2 class="brand-studio__section-title">Main title</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div>
                        <label class="brand-studio__label" for="name_en">English <span class="text-red-500">*</span></label>
                        <input id="name_en" name="name_en" type="text" required value="{{ $nameEn }}"
                            placeholder="e.g. Mini Bag"
                            class="brand-studio__input @error('name_en') brand-studio__input--error @enderror">
                        @error('name_en')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="name_ar">Arabic</label>
                        <input id="name_ar" name="name_ar" type="text" value="{{ $nameAr }}" dir="rtl"
                            placeholder="مثلاً: حقيبة صغيرة"
                            class="brand-studio__input lang-ar @error('name_ar') brand-studio__input--error @enderror">
                        @error('name_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Visual</p>
                <h2 class="brand-studio__section-title">Homepage banner</h2>
                <p class="brand-studio__hint mt-2">
                    Wide editorial cover for the “Shop by category” stack (PNG / WebP / JPG / SVG, up to 5&nbsp;MB),
                    or paste an external image link. Clicking the banner opens this category.
                </p>

                <input type="hidden" name="image_source" id="image_source" value="{{ $imageSource }}">
                <input type="hidden" name="remove_image" id="remove_image" value="0">

                <div class="brand-studio__tabs mt-5" role="tablist">
                    <button type="button" class="brand-studio__tab {{ $imageSource !== 'url' ? 'is-active' : '' }}"
                        data-image-tab="upload" role="tab">
                        <i class="fas fa-cloud-upload-alt"></i> Upload image
                    </button>
                    <button type="button" class="brand-studio__tab {{ $imageSource === 'url' ? 'is-active' : '' }}"
                        data-image-tab="url" role="tab">
                        <i class="fas fa-link"></i> Use link
                    </button>
                </div>

                <div id="image-panel-upload" class="mt-4 {{ $imageSource === 'url' ? 'hidden' : '' }}">
                    <label for="image_file" id="image-dropzone" class="brand-studio__dropzone brand-studio__dropzone--wide">
                        <input type="file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg"
                            class="sr-only">
                        <div id="image-dropzone-empty" class="brand-studio__dropzone-empty {{ $isEdit && $imageUrl ? 'hidden' : '' }}">
                            <span class="brand-studio__dropzone-icon"><i class="fas fa-image"></i></span>
                            <span class="brand-studio__dropzone-title">Drop banner here, or browse</span>
                            <span class="brand-studio__dropzone-meta">Recommended 2400×1000 · wide editorial banner</span>
                        </div>
                        <div id="image-dropzone-preview" class="brand-studio__dropzone-preview {{ $isEdit && $imageUrl ? '' : 'hidden' }}">
                            <div class="brand-studio__dropzone-frame brand-studio__dropzone-frame--wide">
                                <img id="image-preview-img" alt="Banner preview"
                                    decoding="async"
                                    @if ($imageUrl) src="{{ $imageUrl }}" loading="eager" @endif>
                            </div>
                            <span class="brand-studio__dropzone-change">Click or drop to replace</span>
                            <p id="image-preview-meta" class="brand-studio__dropzone-filemeta" hidden></p>
                        </div>
                        <div id="image-progress" class="brand-studio__progress" hidden aria-live="polite">
                            <div class="brand-studio__progress-panel">
                                <div class="brand-studio__progress-head">
                                    <span id="image-progress-label">Preparing preview…</span>
                                    <span id="image-progress-pct">0%</span>
                                </div>
                                <div class="brand-studio__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <span id="image-progress-bar" class="brand-studio__progress-bar"></span>
                                </div>
                            </div>
                        </div>
                    </label>
                    @error('image_file')
                        <p class="brand-studio__error mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div id="image-panel-url" class="mt-4 {{ $imageSource === 'url' ? '' : 'hidden' }}">
                    <label class="brand-studio__label" for="image_url">Banner URL</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-link text-zibra-ash text-sm"></i>
                        </div>
                        <input id="image_url" name="image_url" type="url" value="{{ $imageUrl }}"
                            placeholder="https://cdn.example.com/category-banner.jpg"
                            class="brand-studio__input pl-10 @error('image_url') brand-studio__input--error @enderror">
                    </div>
                    <p id="image-url-status" class="brand-studio__hint mt-2" hidden></p>
                    <p class="brand-studio__hint mt-1.5">Must be a direct, publicly reachable image URL.</p>
                    @error('image_url')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror

                    <div id="image-url-preview" class="brand-studio__url-preview mt-4 {{ $imageSource === 'url' && $imageUrl ? '' : 'hidden' }}">
                        <div class="brand-studio__url-preview-frame brand-studio__url-preview-frame--wide">
                            <img id="image-url-preview-img" alt="URL preview" decoding="async"
                                @if ($imageSource === 'url' && $imageUrl) src="{{ $imageUrl }}" @endif>
                        </div>
                    </div>
                </div>

                <button type="button" id="image-clear" class="studio-slug__tool mt-3 {{ ($isEdit && $imageUrl) || old('image_url') ? '' : 'hidden' }}">
                    <i class="fas fa-trash-alt"></i> Remove banner
                </button>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Mark</p>
                <h2 class="brand-studio__section-title">Homepage logo</h2>
                <p class="brand-studio__hint mt-2">
                    Square editorial mark for the rail under the hero (PNG / WebP / JPG / SVG, up to 5&nbsp;MB).
                    Prefer a clean transparent PNG or a tightly cropped product still — 1200×1200 or larger.
                    Each logo links to this category. Leave empty to keep it off the rail.
                </p>

                <input type="hidden" name="logo_source" id="logo_source" value="{{ $logoSource }}">
                <input type="hidden" name="remove_logo" id="remove_logo" value="0">

                <div class="brand-studio__tabs mt-5" role="tablist">
                    <button type="button" class="brand-studio__tab {{ $logoSource !== 'url' ? 'is-active' : '' }}"
                        data-logo-tab="upload" role="tab">
                        <i class="fas fa-cloud-upload-alt"></i> Upload logo
                    </button>
                    <button type="button" class="brand-studio__tab {{ $logoSource === 'url' ? 'is-active' : '' }}"
                        data-logo-tab="url" role="tab">
                        <i class="fas fa-link"></i> Use link
                    </button>
                </div>

                <div id="logo-panel-upload" class="mt-4 {{ $logoSource === 'url' ? 'hidden' : '' }}">
                    <label for="logo_file" id="logo-dropzone" class="brand-studio__dropzone">
                        <input type="file" id="logo_file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg"
                            class="sr-only">
                        <div id="logo-dropzone-empty" class="brand-studio__dropzone-empty {{ $isEdit && $logoUrl ? 'hidden' : '' }}">
                            <span class="brand-studio__dropzone-icon"><i class="fas fa-certificate"></i></span>
                            <span class="brand-studio__dropzone-title">Drop logo here, or browse</span>
                            <span class="brand-studio__dropzone-meta">Recommended 1200×1200 · transparent PNG preferred</span>
                        </div>
                        <div id="logo-dropzone-preview" class="brand-studio__dropzone-preview {{ $isEdit && $logoUrl ? '' : 'hidden' }}">
                            <img id="logo-preview-img" alt="Logo preview" decoding="async"
                                @if ($logoUrl) src="{{ $logoUrl }}" loading="eager" @endif>
                            <span class="brand-studio__dropzone-change">Click or drop to replace</span>
                        </div>
                    </label>
                    @error('logo_file')
                        <p class="brand-studio__error mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div id="logo-panel-url" class="mt-4 {{ $logoSource === 'url' ? '' : 'hidden' }}">
                    <label class="brand-studio__label" for="logo_url">Logo URL</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-link text-zibra-ash text-sm"></i>
                        </div>
                        <input id="logo_url" name="logo_url" type="url" value="{{ $logoUrl }}"
                            placeholder="https://cdn.example.com/category-logo.png"
                            class="brand-studio__input pl-10 @error('logo_url') brand-studio__input--error @enderror">
                    </div>
                    <p class="brand-studio__hint mt-1.5">Must be a direct, publicly reachable image URL.</p>
                    @error('logo_url')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror

                    <div id="logo-url-preview" class="brand-studio__url-preview mt-4 {{ $logoSource === 'url' && $logoUrl ? '' : 'hidden' }}">
                        <div class="brand-studio__url-preview-frame">
                            <img id="logo-url-preview-img" alt="Logo URL preview" decoding="async"
                                @if ($logoSource === 'url' && $logoUrl) src="{{ $logoUrl }}" @endif>
                        </div>
                    </div>
                </div>

                <button type="button" id="logo-clear" class="studio-slug__tool mt-3 {{ ($isEdit && $logoUrl) || old('logo_url') ? '' : 'hidden' }}">
                    <i class="fas fa-trash-alt"></i> Remove logo
                </button>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">System URL</p>
                <h2 class="brand-studio__section-title">Slug control</h2>
                <p class="brand-studio__hint mt-2">
                    Auto-synced from the name while unlocked. Edit freely anytime — values stay unique and storefront-safe.
                </p>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                    <div class="studio-slug" data-slug-locale="en">
                        <div class="studio-slug__head">
                            <label class="brand-studio__label mb-0" for="slug_en">English slug</label>
                            <div class="studio-slug__tools">
                                <button type="button" class="studio-slug__tool" data-slug-sync title="Regenerate from English name">
                                    <i class="fas fa-magic"></i> Sync
                                </button>
                                <button type="button" class="studio-slug__tool is-active" data-slug-lock title="Lock / unlock auto-sync">
                                    <i class="fas fa-lock-open" data-lock-icon></i>
                                    <span data-lock-label>Auto</span>
                                </button>
                            </div>
                        </div>
                        <div class="studio-slug__field">
                            <span class="studio-slug__prefix">/collections/</span>
                            <input id="slug_en" name="slug_en" type="text" value="{{ $slugEn }}"
                                placeholder="mini-bag" autocomplete="off" spellcheck="false"
                                class="brand-studio__input studio-slug__input @error('slug_en') brand-studio__input--error @enderror"
                                data-slug-input>
                        </div>
                        <p class="brand-studio__hint mt-1.5" data-slug-preview>
                            {{ $storefrontBase }}/collections/<span data-slug-live>{{ $slugEn !== '' ? $slugEn : '…' }}</span>
                        </p>
                        @error('slug_en')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="studio-slug" data-slug-locale="ar">
                        <div class="studio-slug__head">
                            <label class="brand-studio__label mb-0" for="slug_ar">Arabic slug</label>
                            <div class="studio-slug__tools">
                                <button type="button" class="studio-slug__tool" data-slug-sync title="Regenerate from Arabic / English name">
                                    <i class="fas fa-magic"></i> Sync
                                </button>
                                <button type="button" class="studio-slug__tool is-active" data-slug-lock title="Lock / unlock auto-sync">
                                    <i class="fas fa-lock-open" data-lock-icon></i>
                                    <span data-lock-label>Auto</span>
                                </button>
                            </div>
                        </div>
                        <div class="studio-slug__field">
                            <span class="studio-slug__prefix">slug·ar</span>
                            <input id="slug_ar" name="slug_ar" type="text" value="{{ $slugAr }}"
                                placeholder="mini-bag" autocomplete="off" spellcheck="false"
                                class="brand-studio__input studio-slug__input @error('slug_ar') brand-studio__input--error @enderror"
                                data-slug-input>
                        </div>
                        <p class="brand-studio__hint mt-1.5">Falls back to the English slug when Arabic letters don’t latinize.</p>
                        @error('slug_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Story</p>
                <h2 class="brand-studio__section-title">Description & order</h2>
                <p class="brand-studio__hint mt-2">
                    A category is classification only (what the item is). Merchandising groups live on
                    Collections — assign products to collections from the product form.
                </p>

                <div class="mt-5">
                    <label class="brand-studio__label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4"
                        placeholder="Short editorial line for this category…"
                        class="brand-studio__input brand-studio__textarea @error('description') brand-studio__input--error @enderror">{{ $description }}</textarea>
                    @error('description')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 max-w-xs">
                    <label class="brand-studio__label" for="position">Position</label>
                    <input id="position" name="position" type="number" min="0" value="{{ $position }}"
                        class="brand-studio__input @error('position') brand-studio__input--error @enderror">
                    <p class="brand-studio__hint mt-1.5">
                        Lower numbers appear first in catalog listings and on the homepage stack.
                    </p>
                    @error('position')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Homepage</p>
                <h2 class="brand-studio__section-title">Shop by category</h2>
                <p class="brand-studio__hint mt-2">
                    A logo always feeds the rail under the hero. Featured controls the
                    “Shop by category” banner stack. Order follows Featured first, then Position.
                </p>

                <div class="studio-visibility mt-5">
                    <input type="hidden" name="is_featured" value="0">
                    <label class="studio-visibility__switch" for="is_featured">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1"
                            class="sr-only peer" @checked($isFeatured)>
                        <span class="studio-visibility__track" aria-hidden="true">
                            <span class="studio-visibility__thumb"></span>
                        </span>
                        <span class="studio-visibility__copy">
                            <span class="studio-visibility__state" data-on {{ $isFeatured ? '' : 'hidden' }}>On — shown in Shop by category</span>
                            <span class="studio-visibility__state" data-off {{ $isFeatured ? 'hidden' : '' }}>Off — logo rail only (if a logo is set)</span>
                            <span class="studio-visibility__hint">Only Featured categories with a banner appear under Shop by category. Logos still show under the hero.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="brand-studio__actions">
                <button type="button" class="brand-studio__btn brand-studio__btn--ghost"
                    onclick="window.location='{{ route('admin.categories.index') }}'">
                    Cancel
                </button>
                <button type="submit" class="brand-studio__btn brand-studio__btn--ink">
                    <i class="fas fa-check"></i>
                    {{ $isEdit ? 'Save category' : 'Create category' }}
                </button>
            </div>
        </div>

        {{-- ─── Right: live storefront-style preview ─── --}}
        <aside class="brand-studio__preview-col">
            <p class="brand-studio__eyebrow">Live preview</p>
            <h2 class="brand-studio__section-title mb-4">Storefront placements</h2>
            <p class="brand-studio__hint mb-6">
                Logo always appears under the hero when set. Banner + Featured power the Shop by category stack.
            </p>

            <article class="brand-tile-preview brand-tile-preview--full" id="category-tile-preview">
                <div class="brand-tile-preview__media" id="category-tile-media">
                    <img id="category-tile-img" alt=""
                        class="{{ $imageUrl ? '' : 'hidden' }}" decoding="async"
                        @if ($imageUrl) src="{{ $imageUrl }}" @endif>
                    <div id="category-tile-fallback" class="brand-tile-preview__fallback {{ $imageUrl ? 'hidden' : '' }}">
                        <span id="category-tile-wordmark">{{ $nameEn !== '' ? $nameEn : 'Category' }}</span>
                    </div>
                    <div id="category-tile-loading" class="brand-tile-preview__loading" hidden>
                        <span class="brand-tile-preview__spinner" aria-hidden="true"></span>
                        <span>Loading preview…</span>
                    </div>
                    <div class="brand-tile-preview__veil"></div>
                    <span class="brand-tile-preview__cta">Shop category</span>
                </div>
                <div class="brand-tile-preview__meta">
                    <p class="brand-tile-preview__label">Banner · Shop by category</p>
                    <h3 id="category-tile-name" class="brand-tile-preview__name">{{ $nameEn !== '' ? $nameEn : 'New category' }}</h3>
                    <p id="category-tile-slug" class="brand-tile-preview__country font-mono">
                        /collections/{{ $slugEn !== '' ? $slugEn : '…' }}
                    </p>
                </div>
            </article>

            <article class="brand-tile-preview mt-6" id="category-logo-preview">
                <div class="brand-tile-preview__media" id="category-logo-media" style="aspect-ratio: 1; background: #fff;">
                    <img id="category-logo-img" alt=""
                        class="{{ $logoUrl ? '' : 'hidden' }}" decoding="async"
                        style="object-fit: contain; padding: 18%;"
                        @if ($logoUrl) src="{{ $logoUrl }}" @endif>
                    <div id="category-logo-fallback" class="brand-tile-preview__fallback {{ $logoUrl ? 'hidden' : '' }}">
                        <span id="category-logo-wordmark">{{ $nameEn !== '' ? $nameEn : 'Logo' }}</span>
                    </div>
                </div>
                <div class="brand-tile-preview__meta">
                    <p class="brand-tile-preview__label">Logo · Under hero</p>
                    <h3 id="category-logo-name" class="brand-tile-preview__name">{{ $nameEn !== '' ? $nameEn : 'New category' }}</h3>
                </div>
            </article>
        </aside>
    </div>
</form>
