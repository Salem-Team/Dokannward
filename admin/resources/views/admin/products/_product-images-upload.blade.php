{{--
    Multi-image product upload: dropzone + reorderable gallery.
    Posts as images[] in visual order (first = main / primary storefront cover).
    Behavior ships from resources/js/product-media.js (Vite admin bundle).
--}}
@php
    $galleryError = $errors->first('images') ?: $errors->first('images.*');
    $galleryHelp = $galleryHelp ?? 'Drop several photos, then click “Set as Main” on the cover that should appear on the website (or drag it to the front).';
@endphp

<div
    class="space-y-4"
    id="product-images-uploader"
    data-product-images-uploader
    data-max-mb="2048"
>
    <div>
        <p class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {{ $galleryLabel ?? 'Product Images' }}
        </p>

        <label
            for="product-images-input"
            id="product-images-dropzone"
            class="product-gallery-dz"
            data-product-images-dz
            tabindex="0"
            aria-label="Upload product images"
        >
            <input
                type="file"
                id="product-images-input"
                name="images[]"
                class="product-gallery-dz__input"
                data-product-images-input
                accept="image/*"
                multiple
            >
            <div class="product-gallery-dz__empty">
                <span class="product-gallery-dz__icon"><i class="fas fa-cloud-arrow-up"></i></span>
                <span class="product-gallery-dz__title">Drop images here, or browse</span>
                <span class="product-gallery-dz__meta">Any image type · large files OK (up to 2&nbsp;GB each)</span>
            </div>
        </label>

        @if ($galleryError)
            <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                <i class="fas fa-exclamation-circle mr-1"></i>
                {{ $galleryError }}
            </p>
        @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $galleryHelp }}</p>
        @endif

        <p id="product-images-status" class="product-gallery-status mt-2" data-product-images-status hidden></p>
    </div>

    <div id="product-images-grid" class="product-gallery-grid" data-product-images-grid hidden role="list" aria-label="Selected product images"></div>
</div>
