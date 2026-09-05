{{--
    Multi-photo picker for one color row.

    Posts as color_images[{{ $inputKey }}][] and, for saved colors, lets the
    admin untick existing photos (remove_color_photos[]) before saving.
    First kept photo is the color cover on the storefront; Set Main also
    promotes it to the website product cover (is_primary).

    Behavior ships from resources/js/product-media.js (Vite admin bundle).

    Props: $inputKey (color id or new-row index), $existingPhotos (collection|null),
           $productId (uuid|null — enables Set Main on saved photos)
--}}
@php
    $existingPhotos = $existingPhotos ?? collect();
    $productId = $productId ?? null;
@endphp

<div class="color-photos" data-color-photos>
    <div class="color-photos__head">
        <span class="color-photos__label">Photos for this color</span>
        <span class="color-photos__count" data-color-photos-count>
            {{ $existingPhotos->count() ?: 'None yet' }}
        </span>
    </div>

    <p class="color-photos__hint">
        Click <strong>Set Main</strong> on any saved photo to make it this color’s cover
        <em>and</em> the website Main image (cards + product page).
    </p>

    @if ($existingPhotos->count())
        <div class="color-photos__grid color-photos__grid--existing" data-color-photos-existing>
            @foreach ($existingPhotos as $photo)
                @php
                    $isColorLead = $loop->first;
                    $isWebsiteMain = (bool) $photo->is_primary;
                @endphp
                <div class="color-photos__tile{{ $isColorLead ? ' is-main' : '' }}{{ $isWebsiteMain ? ' is-website-main' : '' }}"
                    title="{{ $photo->file_name }}"
                    data-photo-id="{{ $photo->id }}"
                    @if ($productId && ! $isWebsiteMain)
                        data-color-main-url="{{ route('admin.products.color-photos.primary', [$productId, $photo->id]) }}"
                    @endif
                >
                    <img src="{{ $photo->url }}" alt="{{ $photo->alt_text }}" loading="lazy">

                    @if ($isWebsiteMain)
                        <span class="color-photos__main-badge is-website" data-color-main-badge>
                            <i class="fas fa-star"></i> Website Main
                        </span>
                    @elseif ($isColorLead)
                        <span class="color-photos__main-badge" data-color-main-badge>
                            <i class="fas fa-star"></i> Color cover
                        </span>
                    @endif

                    @if ($productId && ! $isWebsiteMain)
                        <button
                            type="button"
                            class="color-photos__make-main is-always-on"
                            data-set-color-main="{{ route('admin.products.color-photos.primary', [$productId, $photo->id]) }}"
                            title="Use as this color’s cover and the website Main image"
                        >Set Main</button>
                    @endif

                    <input type="checkbox" name="remove_color_photos[]" value="{{ $photo->id }}"
                        id="remove-color-photo-{{ $photo->id }}"
                        class="color-photos__remove-input" data-color-photo-remove>
                    <label for="remove-color-photo-{{ $photo->id }}" class="color-photos__remove" title="Mark for removal" aria-label="Mark for removal">
                        <i class="fas fa-times"></i>
                    </label>
                    <span class="color-photos__flag">Remove</span>
                </div>
            @endforeach
        </div>
    @endif

    <label for="color-photos-input-{{ $inputKey }}" class="color-photos__dz" data-color-photos-dz tabindex="0"
        aria-label="Add photos for this color">
        <input type="file" id="color-photos-input-{{ $inputKey }}" name="color_images[{{ $inputKey }}][]"
            accept="image/*"
            multiple class="color-photos__input" data-color-photos-input>
        <i class="fas fa-images"></i>
        <span>Drop photos or <b>browse</b></span>
        <small>Any image type · Set Main after save · up to 2&nbsp;GB each</small>
    </label>

    <div class="color-photos__grid" data-color-photos-preview hidden></div>
    <p class="color-photos__status" data-color-photos-status hidden></p>
</div>
