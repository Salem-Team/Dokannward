@php
    $isEdit = isset($brand);
    $nameEn = old('name_en', $isEdit ? (is_array($brand->name) ? ($brand->name['en'] ?? '') : $brand->name) : '');
    $nameAr = old('name_ar', $isEdit ? (is_array($brand->name) ? ($brand->name['ar'] ?? '') : '') : '');
    $descEn = old('description_en', $isEdit ? (is_array($brand->description) ? ($brand->description['en'] ?? '') : '') : '');
    $descAr = old('description_ar', $isEdit ? (is_array($brand->description) ? ($brand->description['ar'] ?? '') : '') : '');
    $country = old('country', $isEdit ? $brand->country : '');
    $logoUrl = old('logo_url', $isEdit ? $brand->logo_url : '');
    $logoSource = old('logo_source', 'upload');
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="brand-studio" data-turbo="false">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="brand-studio__grid">
        {{-- ─── Left: editor ─── --}}
        <div class="brand-studio__editor space-y-8">
            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Identity</p>
                <h2 class="brand-studio__section-title">Brand name</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div>
                        <label class="brand-studio__label" for="name_en">English <span class="text-red-500">*</span></label>
                        <input id="name_en" name="name_en" type="text" required value="{{ $nameEn }}"
                            placeholder="e.g. Alo Yoga"
                            class="brand-studio__input @error('name_en') brand-studio__input--error @enderror">
                        @error('name_en')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="name_ar">Arabic</label>
                        <input id="name_ar" name="name_ar" type="text" value="{{ $nameAr }}" dir="rtl"
                            placeholder="مثلاً: ألو يوجا"
                            class="brand-studio__input lang-ar @error('name_ar') brand-studio__input--error @enderror">
                        @error('name_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <label class="brand-studio__label" for="country">Country of origin</label>
                    <input id="country" name="country" type="text" value="{{ $country }}"
                        placeholder="e.g. Italy, France, USA"
                        class="brand-studio__input @error('country') brand-studio__input--error @enderror">
                    @error('country')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5">
                    <label class="brand-studio__label" for="slug_display">Slug</label>
                    <input id="slug_display" type="text" disabled
                        value="{{ $isEdit ? ($brand->slug ?: '—') : 'Auto-generated · unique · locked' }}"
                        class="brand-studio__input opacity-70 cursor-not-allowed">
                    <p class="brand-studio__hint mt-1.5">Built from the English name once — never edited by hand.</p>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Story</p>
                <h2 class="brand-studio__section-title">Description</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <div>
                        <label class="brand-studio__label" for="description_en">English</label>
                        <textarea id="description_en" name="description_en" rows="5"
                            placeholder="A short house story for the collections page…"
                            class="brand-studio__input brand-studio__textarea @error('description_en') brand-studio__input--error @enderror">{{ $descEn }}</textarea>
                        @error('description_en')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="description_ar">Arabic</label>
                        <textarea id="description_ar" name="description_ar" rows="5" dir="rtl"
                            placeholder="نبذة قصيرة عن العلامة…"
                            class="brand-studio__input brand-studio__textarea lang-ar @error('description_ar') brand-studio__input--error @enderror">{{ $descAr }}</textarea>
                        @error('description_ar')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Mark</p>
                <h2 class="brand-studio__section-title">Brand logo</h2>
                <p class="brand-studio__hint mt-2">
                    Upload a high-resolution mark (PNG / WebP / JPG / SVG, up to 5&nbsp;MB), or paste an external image link.
                </p>

                <input type="hidden" name="logo_source" id="logo_source" value="{{ $logoSource }}">

                <div class="brand-studio__tabs mt-5" role="tablist">
                    <button type="button" class="brand-studio__tab {{ $logoSource !== 'url' ? 'is-active' : '' }}"
                        data-logo-tab="upload" role="tab">
                        <i class="fas fa-cloud-upload-alt"></i> Upload image
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
                            <span class="brand-studio__dropzone-icon"><i class="fas fa-image"></i></span>
                            <span class="brand-studio__dropzone-title">Drop logo here, or browse</span>
                            <span class="brand-studio__dropzone-meta">Recommended 1200×1200 · transparent PNG preferred</span>
                        </div>
                        <div id="logo-dropzone-preview" class="brand-studio__dropzone-preview {{ $isEdit && $logoUrl ? '' : 'hidden' }}">
                            <img id="logo-preview-img" src="{{ $logoUrl }}" alt="Logo preview">
                            <span class="brand-studio__dropzone-change">Click or drop to replace</span>
                        </div>
                    </label>
                    @error('logo_file')
                        <p class="brand-studio__error mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div id="logo-panel-url" class="mt-4 {{ $logoSource === 'url' ? '' : 'hidden' }}">
                    <label class="brand-studio__label" for="logo_url">Image URL</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-link text-zibra-ash text-sm"></i>
                        </div>
                        <input id="logo_url" name="logo_url" type="url" value="{{ $logoUrl }}"
                            placeholder="https://cdn.example.com/logo.png"
                            class="brand-studio__input pl-10 @error('logo_url') brand-studio__input--error @enderror">
                    </div>
                    <p class="brand-studio__hint mt-1.5">Must be a direct, publicly reachable image URL.</p>
                    @error('logo_url')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <div class="brand-studio__actions">
                <button type="button" class="brand-studio__btn brand-studio__btn--ghost"
                    onclick="window.location='{{ route('admin.brands.index') }}'">
                    Cancel
                </button>
                <button type="submit" class="brand-studio__btn brand-studio__btn--ink">
                    <i class="fas fa-check"></i>
                    {{ $isEdit ? 'Save brand' : 'Create brand' }}
                </button>
            </div>
        </div>

        {{-- ─── Right: live storefront-style preview ─── --}}
        <aside class="brand-studio__preview-col">
            <p class="brand-studio__eyebrow">Live preview</p>
            <h2 class="brand-studio__section-title mb-4">Collections tile</h2>
            <p class="brand-studio__hint mb-6">Matches how the house appears on the public site.</p>

            <article class="brand-tile-preview" id="brand-tile-preview">
                <div class="brand-tile-preview__media" id="brand-tile-media">
                    <img id="brand-tile-img" src="{{ $logoUrl }}" alt="" class="{{ $logoUrl ? '' : 'hidden' }}">
                    <div id="brand-tile-fallback" class="brand-tile-preview__fallback {{ $logoUrl ? 'hidden' : '' }}">
                        <span id="brand-tile-wordmark">{{ $nameEn !== '' ? $nameEn : 'Brand' }}</span>
                    </div>
                    <div class="brand-tile-preview__veil"></div>
                    <span class="brand-tile-preview__cta">View collection</span>
                </div>
                <div class="brand-tile-preview__meta">
                    <p class="brand-tile-preview__label">The house</p>
                    <h3 id="brand-tile-name" class="brand-tile-preview__name">{{ $nameEn !== '' ? $nameEn : 'New brand' }}</h3>
                    <p id="brand-tile-country" class="brand-tile-preview__country">{{ $country !== '' ? $country : 'Origin pending' }}</p>
                </div>
            </article>
        </aside>
    </div>
</form>
