@extends('admin.layouts.app')

@section('title', 'Homepage')

@section('content')
    @include('admin.website._shell-start')

    <form action="{{ route('admin.website.update', 'home') }}" method="POST" class="brand-studio brand-studio--full space-y-6">
        @csrf
        @method('PUT')

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">First impression</p>
                <h2 class="brand-studio__section-title">Homepage hero</h2>
                <p class="brand-studio__hint mt-1">Full-bleed plate and wordmark at the top of the storefront. Use a site path (<code class="text-xs">/images/…</code>) or full URL.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="hero_image">Background image</label>
                    <input id="hero_image" name="hero_image" type="text"
                        value="{{ old('hero_image', $content['hero_image'] ?? '') }}"
                        class="brand-studio__input" placeholder="/images/hero-layers/hero-base.jpg">
                </div>
                <div>
                    <label class="brand-studio__label" for="hero_wordmark">Wordmark overlay</label>
                    <input id="hero_wordmark" name="hero_wordmark" type="text"
                        value="{{ old('hero_wordmark', $content['hero_wordmark'] ?? '') }}"
                        class="brand-studio__input" placeholder="/images/hero-layers/wordmark.svg">
                </div>
            </div>
            <div>
                <label class="brand-studio__label" for="hero_alt">Accessibility / alt text</label>
                <input id="hero_alt" name="hero_alt" type="text"
                    value="{{ old('hero_alt', $content['hero_alt'] ?? '') }}"
                    class="brand-studio__input" placeholder="DOKAN WARD — Express your elegance">
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Products section</p>
                <h2 class="brand-studio__section-title">New arrivals</h2>
                <p class="brand-studio__hint mt-1">Appears above the newest products on the homepage.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="brand-studio__label" for="arrivals_eyebrow">Small label</label>
                    <input id="arrivals_eyebrow" name="arrivals_eyebrow" type="text"
                        value="{{ old('arrivals_eyebrow', $content['arrivals_eyebrow'] ?? '') }}"
                        class="brand-studio__input" placeholder="Just in">
                </div>
                <div>
                    <label class="brand-studio__label" for="arrivals_title">Main title</label>
                    <input id="arrivals_title" name="arrivals_title" type="text"
                        value="{{ old('arrivals_title', $content['arrivals_title'] ?? '') }}"
                        class="brand-studio__input" placeholder="New arrivals">
                </div>
                <div>
                    <label class="brand-studio__label" for="arrivals_link_label">Link text</label>
                    <input id="arrivals_link_label" name="arrivals_link_label" type="text"
                        value="{{ old('arrivals_link_label', $content['arrivals_link_label'] ?? '') }}"
                        class="brand-studio__input" placeholder="View all">
                </div>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Social proof</p>
                <h2 class="brand-studio__section-title">Customer stories</h2>
                <p class="brand-studio__hint mt-1">Titles above the testimonials. Add/edit stories under Content → Testimonials.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="testimonials_eyebrow">Small label</label>
                    <input id="testimonials_eyebrow" name="testimonials_eyebrow" type="text"
                        value="{{ old('testimonials_eyebrow', $content['testimonials_eyebrow'] ?? '') }}"
                        class="brand-studio__input" placeholder="Client voices">
                </div>
                <div>
                    <label class="brand-studio__label" for="testimonials_title">Main title</label>
                    <input id="testimonials_title" name="testimonials_title" type="text"
                        value="{{ old('testimonials_title', $content['testimonials_title'] ?? '') }}"
                        class="brand-studio__input" placeholder="What our clients say">
                </div>
            </div>
        </section>

        <div className="website-hub__tip">
            <i class="fas fa-image" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">Mid-page banners &amp; collections</p>
                <p class="website-hub__tip-text">
                    Hero plate and wordmark are edited above. Mid-page plates live in
                    <a href="{{ route('admin.banners.index') }}" class="underline underline-offset-2">Banners</a>
                    and collection covers in
                    <a href="{{ route('admin.collections.index') }}" class="underline underline-offset-2">Collections</a>.
                    Header/footer logos are under
                    <a href="{{ route('admin.settings.index') }}" class="underline underline-offset-2">Settings → General</a>.
                </p>
            </div>
        </div>

        <div class="flex justify-end">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save homepage</x-admin.button>
        </div>
    </form>
</div>
@endsection
