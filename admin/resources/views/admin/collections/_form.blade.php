@php
    $isEdit = isset($collection);
    $nameEn = old('name_en', $isEdit ? (is_array($collection->name) ? ($collection->name['en'] ?? '') : $collection->name) : '');
    $nameAr = old('name_ar', $isEdit ? (is_array($collection->name) ? ($collection->name['ar'] ?? '') : '') : '');
    $slugEn = old('slug_en', $isEdit ? (is_array($collection->slug) ? ($collection->slug['en'] ?? '') : $collection->slug) : '');
    $slugAr = old('slug_ar', $isEdit ? (is_array($collection->slug) ? ($collection->slug['ar'] ?? '') : '') : '');
    $description = old('description', $isEdit ? $collection->description : '');
    $position = old('position', $isEdit ? $collection->position : 0);
    $imageUrl = old('image_url', $isEdit ? $collection->image_url : '');
    $imageSource = old('image_source', 'upload');
    if (old('is_published') !== null) {
        $isPublished = filter_var(old('is_published'), FILTER_VALIDATE_BOOLEAN);
    } elseif ($isEdit) {
        $isPublished = (bool) $collection->is_published;
    } else {
        $isPublished = true;
    }
    $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="brand-studio brand-studio--full" data-turbo="false">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="brand-studio__grid brand-studio__grid--wide">
        <div class="brand-studio__editor space-y-8">
            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Identity</p>
                <h2 class="brand-studio__section-title">Main title</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div>
                        <label class="brand-studio__label" for="name_en">English <span class="text-red-500">*</span></label>
                        <input id="name_en" name="name_en" type="text" required value="{{ $nameEn }}"
                            placeholder="e.g. Bags"
                            class="brand-studio__input @error('name_en') brand-studio__input--error @enderror">
                        @error('name_en')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="name_ar">Arabic</label>
                        <input id="name_ar" name="name_ar" type="text" value="{{ $nameAr }}" dir="rtl"
                            placeholder="مثلاً: حقائب"
                            class="brand-studio__input lang-ar @error('name_ar') brand-studio__input--error @enderror">
                        @error('name_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Visual</p>
                <h2 class="brand-studio__section-title">Collection image</h2>
                <p class="brand-studio__hint mt-2">
                    Upload a high-resolution cover (PNG / WebP / JPG / SVG, up to 5&nbsp;MB), or paste an external image link.
                    This image powers the storefront collection tile.
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
                            <span class="brand-studio__dropzone-title">Drop image here, or browse</span>
                            <span class="brand-studio__dropzone-meta">Recommended 1600×2000 · editorial cover</span>
                        </div>
                        <div id="image-dropzone-preview" class="brand-studio__dropzone-preview {{ $isEdit && $imageUrl ? '' : 'hidden' }}">
                            <div class="brand-studio__dropzone-frame">
                                <img id="image-preview-img" alt="Collection preview"
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
                    <label class="brand-studio__label" for="image_url">Image URL</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-link text-zibra-ash text-sm"></i>
                        </div>
                        <input id="image_url" name="image_url" type="url" value="{{ $imageUrl }}"
                            placeholder="https://cdn.example.com/collection.jpg"
                            class="brand-studio__input pl-10 @error('image_url') brand-studio__input--error @enderror">
                    </div>
                    <p id="image-url-status" class="brand-studio__hint mt-2" hidden></p>
                    @error('image_url')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror

                    <div id="image-url-preview" class="brand-studio__url-preview mt-4 {{ $imageSource === 'url' && $imageUrl ? '' : 'hidden' }}">
                        <div class="brand-studio__url-preview-frame">
                            <img id="image-url-preview-img" alt="URL preview" decoding="async"
                                @if ($imageSource === 'url' && $imageUrl) src="{{ $imageUrl }}" @endif>
                        </div>
                    </div>
                </div>

                <button type="button" id="image-clear" class="studio-slug__tool mt-3 {{ ($isEdit && $imageUrl) || old('image_url') ? '' : 'hidden' }}">
                    <i class="fas fa-trash-alt"></i> Remove image
                </button>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">System URL</p>
                <h2 class="brand-studio__section-title">Slug control</h2>
                <p class="brand-studio__hint mt-2">
                    Auto-synced from the title while unlocked. Values stay unique and storefront-safe.
                </p>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                    <div class="studio-slug" data-slug-locale="en">
                        <div class="studio-slug__head">
                            <label class="brand-studio__label mb-0" for="slug_en">English slug</label>
                            <div class="studio-slug__tools">
                                <button type="button" class="studio-slug__tool" data-slug-sync title="Regenerate from English title">
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
                                placeholder="bags" autocomplete="off" spellcheck="false"
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
                                <button type="button" class="studio-slug__tool" data-slug-sync>
                                    <i class="fas fa-magic"></i> Sync
                                </button>
                                <button type="button" class="studio-slug__tool is-active" data-slug-lock>
                                    <i class="fas fa-lock-open" data-lock-icon></i>
                                    <span data-lock-label>Auto</span>
                                </button>
                            </div>
                        </div>
                        <div class="studio-slug__field">
                            <span class="studio-slug__prefix">slug·ar</span>
                            <input id="slug_ar" name="slug_ar" type="text" value="{{ $slugAr }}"
                                placeholder="bags" autocomplete="off" spellcheck="false"
                                class="brand-studio__input studio-slug__input @error('slug_ar') brand-studio__input--error @enderror"
                                data-slug-input>
                        </div>
                        @error('slug_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Story</p>
                <h2 class="brand-studio__section-title">Description</h2>

                <div class="mt-5">
                    <label class="brand-studio__label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4"
                        placeholder="Short editorial line for the collection page…"
                        class="brand-studio__input brand-studio__textarea @error('description') brand-studio__input--error @enderror">{{ $description }}</textarea>
                    @error('description')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 max-w-xs">
                    <label class="brand-studio__label" for="position">Position</label>
                    <input id="position" name="position" type="number" min="0" value="{{ $position }}"
                        class="brand-studio__input @error('position') brand-studio__input--error @enderror">
                    <p class="brand-studio__hint mt-1.5">Lower numbers appear first.</p>
                    @error('position')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Storefront</p>
                <h2 class="brand-studio__section-title">Visibility</h2>
                <p class="brand-studio__hint mt-2">
                    When on, this collection appears on the public site with its title, cover, description,
                    categories, brands, and products.
                </p>

                <div class="studio-visibility mt-5">
                    <input type="hidden" name="is_published" value="0">
                    <label class="studio-visibility__switch" for="is_published">
                        <input type="checkbox" id="is_published" name="is_published" value="1"
                            class="sr-only peer" @checked($isPublished)>
                        <span class="studio-visibility__track" aria-hidden="true">
                            <span class="studio-visibility__thumb"></span>
                        </span>
                        <span class="studio-visibility__copy">
                            <span class="studio-visibility__state" data-on {{ $isPublished ? '' : 'hidden' }}>On — visible on website</span>
                            <span class="studio-visibility__state" data-off {{ $isPublished ? 'hidden' : '' }}>Off — hidden from website</span>
                            <span class="studio-visibility__hint">Toggle anytime. Changes publish after save.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="brand-studio__actions">
                <button type="button" class="brand-studio__btn brand-studio__btn--ghost"
                    onclick="window.location='{{ route('admin.collections.index') }}'">
                    Cancel
                </button>
                <button type="submit" class="brand-studio__btn brand-studio__btn--ink">
                    <i class="fas fa-check"></i>
                    {{ $isEdit ? 'Save collection' : 'Create collection' }}
                </button>
            </div>
        </div>

        <aside class="brand-studio__preview-col">
            <p class="brand-studio__eyebrow">Live preview</p>
            <h2 class="brand-studio__section-title mb-4">Collection tile</h2>
            <p class="brand-studio__hint mb-6">Matches how the collection appears on the public site.</p>

            <article class="brand-tile-preview" id="collection-tile-preview">
                <div class="brand-tile-preview__media" id="collection-tile-media">
                    <img id="collection-tile-img" alt=""
                        class="{{ $imageUrl ? '' : 'hidden' }}" decoding="async"
                        @if ($imageUrl) src="{{ $imageUrl }}" @endif>
                    <div id="collection-tile-fallback" class="brand-tile-preview__fallback {{ $imageUrl ? 'hidden' : '' }}">
                        <span id="collection-tile-wordmark">{{ $nameEn !== '' ? $nameEn : 'Collection' }}</span>
                    </div>
                    <div id="collection-tile-loading" class="brand-tile-preview__loading" hidden>
                        <span class="brand-tile-preview__spinner" aria-hidden="true"></span>
                        <span>Loading preview…</span>
                    </div>
                    <div class="brand-tile-preview__veil"></div>
                    <span class="brand-tile-preview__cta">View collection</span>
                </div>
                <div class="brand-tile-preview__meta">
                    <p class="brand-tile-preview__label">Collection</p>
                    <h3 id="collection-tile-name" class="brand-tile-preview__name">{{ $nameEn !== '' ? $nameEn : 'New collection' }}</h3>
                    <p id="collection-tile-slug" class="brand-tile-preview__country font-mono">
                        /collections/{{ $slugEn !== '' ? $slugEn : '…' }}
                    </p>
                </div>
            </article>
        </aside>
    </div>
</form>
