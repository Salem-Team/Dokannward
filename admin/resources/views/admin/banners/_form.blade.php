@php
    $isEdit = isset($banner);
    $title = old('title', $isEdit ? ($banner->title ?? '') : '');
    $subtitle = old('subtitle', $isEdit ? ($banner->subtitle ?? '') : '');
    $buttonText = old('button_text', $isEdit ? ($banner->button_text ?? 'Shop now') : 'Shop now');
    $buttonUrl = old('button_url', $isEdit ? ($banner->button_url ?? '/collections/all') : '/collections/all');
    $imageUrl = old('image_url', $isEdit ? ($banner->image_url ?? '') : '');
    $imageSource = old('image_source', 'upload');
    $position = old('position', $isEdit ? ($banner->position ?? 0) : 0);
    $startAt = old('start_at', $isEdit && $banner->start_at ? $banner->start_at->format('Y-m-d\TH:i') : '');
    $endAt = old('end_at', $isEdit && $banner->end_at ? $banner->end_at->format('Y-m-d\TH:i') : '');
    $isActive = (bool) old('is_active', $isEdit ? $banner->is_active : true);
    $storefrontBase = rtrim((string) ($storefrontBase ?? config('app.frontend_url')), '/');
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="brand-studio brand-studio--full" data-turbo="false">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <aside class="banner-integration mb-8" aria-label="Storefront integration">
        <div class="banner-integration__copy">
            <p class="banner-integration__eyebrow">Website integration</p>
            <h3 class="banner-integration__title">Homepage CTA plates</h3>
            <p class="banner-integration__text">
                Live banners sync to <code>GET /api/banners</code> and render on
                <strong>{{ $storefrontBase ?: 'dokannward.com' }}/</strong> as the two full-bleed
                editorial CTAs under the hero. Position <strong>0</strong> = primary,
                <strong>1</strong> = secondary. Inactive or out-of-window banners never leave the API.
            </p>
        </div>
        <div class="banner-integration__slots" aria-hidden="true">
            <span class="banner-integration__slot is-primary">Slot 0 · Primary</span>
            <span class="banner-integration__slot is-secondary">Slot 1 · Secondary</span>
        </div>
        @if ($storefrontBase)
            <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener" class="banner-integration__live">
                View homepage <i class="fas fa-external-link-alt"></i>
            </a>
        @endif
    </aside>

    <div class="brand-studio__grid brand-studio__grid--wide">
        <div class="brand-studio__editor space-y-8">
            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Copy</p>
                <h2 class="brand-studio__section-title">Message & CTA</h2>

                <div class="mt-5 space-y-5">
                    <div>
                        <label class="brand-studio__label" for="title">Title</label>
                        <input id="title" name="title" type="text" value="{{ $title }}"
                            placeholder="e.g. curated essentials."
                            class="brand-studio__input @error('title') brand-studio__input--error @enderror">
                        @error('title')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="brand-studio__label" for="subtitle">Subtitle</label>
                        <textarea id="subtitle" name="subtitle" rows="3"
                            placeholder="Short supporting line under the title…"
                            class="brand-studio__input brand-studio__textarea @error('subtitle') brand-studio__input--error @enderror">{{ $subtitle }}</textarea>
                        @error('subtitle')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="brand-studio__label" for="button_text">Button text</label>
                            <input id="button_text" name="button_text" type="text" value="{{ $buttonText }}"
                                placeholder="Shop now"
                                class="brand-studio__input @error('button_text') brand-studio__input--error @enderror">
                            @error('button_text')
                                <p class="brand-studio__error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="brand-studio__label" for="button_url">Button link</label>
                            <input id="button_url" name="button_url" type="text" value="{{ $buttonUrl }}"
                                placeholder="/collections/all"
                                class="brand-studio__input @error('button_url') brand-studio__input--error @enderror">
                            <p class="brand-studio__hint mt-1.5">Site path (<code>/collections/all</code>) or full https URL.</p>
                            @error('button_url')
                                <p class="brand-studio__error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Visual</p>
                <h2 class="brand-studio__section-title">Background image</h2>
                <p class="brand-studio__hint mt-2">
                    Wide editorial cover (JPEG / PNG / WebP, up to 5&nbsp;MB). Shown full-bleed on the homepage.
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
                        <i class="fas fa-link"></i> Use link / path
                    </button>
                </div>

                <div id="image-panel-upload" class="mt-4 {{ $imageSource === 'url' ? 'hidden' : '' }}">
                    <label for="image_file" id="image-dropzone" class="brand-studio__dropzone brand-studio__dropzone--wide">
                        <input type="file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif"
                            class="sr-only">
                        <div id="image-dropzone-empty" class="brand-studio__dropzone-empty {{ $imageUrl ? 'hidden' : '' }}">
                            <span class="brand-studio__dropzone-icon"><i class="fas fa-image"></i></span>
                            <span class="brand-studio__dropzone-title">Drop image here, or browse</span>
                            <span class="brand-studio__dropzone-meta">Recommended 1920×1280 · landscape</span>
                        </div>
                        <div id="image-dropzone-preview" class="brand-studio__dropzone-preview {{ $imageUrl ? '' : 'hidden' }}">
                            <div class="brand-studio__dropzone-frame brand-studio__dropzone-frame--wide">
                                <img id="image-preview-img" alt="Banner preview" decoding="async"
                                    @if ($imageUrl) src="{{ $imageUrl }}" @endif>
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
                    <label class="brand-studio__label" for="image_url">Image URL or path</label>
                    <input id="image_url" name="image_url" type="text" value="{{ $imageUrl }}"
                        placeholder="https://… or /images/hero.jpg"
                        class="brand-studio__input @error('image_url') brand-studio__input--error @enderror">
                    <p id="image-url-status" class="brand-studio__hint mt-2" hidden></p>
                    @error('image_url')
                        <p class="brand-studio__error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="button" id="image-clear" class="studio-slug__tool mt-3 {{ $imageUrl ? '' : 'hidden' }}">
                    <i class="fas fa-trash-alt"></i> Remove image
                </button>
            </section>

            <section class="brand-studio__section">
                <p class="brand-studio__eyebrow">Placement & schedule</p>
                <h2 class="brand-studio__section-title">When it goes live</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-5">
                    <div>
                        <label class="brand-studio__label" for="position">Position</label>
                        <input id="position" name="position" type="number" min="0" max="99" value="{{ $position }}"
                            class="brand-studio__input @error('position') brand-studio__input--error @enderror">
                        <p class="brand-studio__hint mt-1.5" id="position-hint">0 = primary · 1 = secondary</p>
                        @error('position')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="start_at">Starts at</label>
                        <input id="start_at" name="start_at" type="datetime-local" value="{{ $startAt }}"
                            class="brand-studio__input @error('start_at') brand-studio__input--error @enderror">
                        @error('start_at')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label" for="end_at">Ends at</label>
                        <input id="end_at" name="end_at" type="datetime-local" value="{{ $endAt }}"
                            class="brand-studio__input @error('end_at') brand-studio__input--error @enderror">
                        @error('end_at')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="studio-visibility mt-6">
                    <input type="hidden" name="is_active" value="0">
                    <label class="studio-visibility__switch" for="is_active">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                            class="sr-only peer" @checked($isActive)>
                        <span class="studio-visibility__track" aria-hidden="true">
                            <span class="studio-visibility__thumb"></span>
                        </span>
                        <span class="studio-visibility__copy">
                            <span class="studio-visibility__state" data-on {{ $isActive ? '' : 'hidden' }}>On — eligible for homepage</span>
                            <span class="studio-visibility__state" data-off {{ $isActive ? 'hidden' : '' }}>Off — hidden from website</span>
                            <span class="studio-visibility__hint">Must also fall inside the optional date window to appear live.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="brand-studio__actions">
                <button type="button" class="brand-studio__btn brand-studio__btn--ghost"
                    onclick="window.location='{{ route('admin.banners.index') }}'">
                    Cancel
                </button>
                <button type="submit" class="brand-studio__btn brand-studio__btn--ink">
                    <i class="fas fa-check"></i>
                    {{ $isEdit ? 'Save banner' : 'Create banner' }}
                </button>
            </div>
        </div>

        <aside class="brand-studio__preview-col">
            <p class="brand-studio__eyebrow">Live preview</p>
            <h2 class="brand-studio__section-title mb-2">Homepage plate</h2>
            <p class="brand-studio__hint mb-5">Matches the storefront CTA overlay on dokannward.com.</p>

            <article class="banner-live-preview" id="banner-live-preview">
                <div class="banner-live-preview__media" id="banner-preview-media">
                    <img id="banner-preview-img" alt="" class="{{ $imageUrl ? '' : 'hidden' }}" decoding="async"
                        @unless ($imageUrl) hidden @endunless
                        @if ($imageUrl) src="{{ $imageUrl }}" @endif>
                    <div id="banner-preview-fallback" class="banner-live-preview__fallback {{ $imageUrl ? 'hidden' : '' }}"
                        @if ($imageUrl) hidden @endif>
                        Background image
                    </div>
                    <div class="banner-live-preview__veil"></div>
                    <div class="banner-live-preview__copy">
                        <h3 id="banner-preview-title" class="banner-live-preview__title">
                            {{ $title !== '' ? $title : 'Banner title' }}
                        </h3>
                        <p id="banner-preview-subtitle" class="banner-live-preview__subtitle">
                            {{ $subtitle !== '' ? $subtitle : 'Supporting line appears here.' }}
                        </p>
                        <span id="banner-preview-cta" class="banner-live-preview__cta">
                            {{ $buttonText !== '' ? $buttonText : 'Shop now' }}
                        </span>
                    </div>
                </div>
                <p class="banner-live-preview__meta" id="banner-preview-meta">
                    {{ (int) $position <= 0 ? 'Homepage · Primary CTA' : ((int) $position === 1 ? 'Homepage · Secondary CTA' : 'Extra slot') }}
                </p>
            </article>
        </aside>
    </div>
</form>
