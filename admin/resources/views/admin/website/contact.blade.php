@extends('admin.layouts.app')

@section('title', 'Contact page')

@section('content')
    @include('admin.website._shell-start')

    <form action="{{ route('admin.website.update', 'contact') }}" method="POST" class="brand-studio brand-studio--full space-y-6">
        @csrf
        @method('PUT')

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Page intro</p>
                <h2 class="brand-studio__section-title">What visitors read first</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="eyebrow">Small label</label>
                    <input id="eyebrow" name="eyebrow" type="text"
                        value="{{ old('eyebrow', $content['eyebrow'] ?? '') }}"
                        class="brand-studio__input" placeholder="Get in touch">
                </div>
                <div>
                    <label class="brand-studio__label" for="title">Page title</label>
                    <input id="title" name="title" type="text"
                        value="{{ old('title', $content['title'] ?? '') }}"
                        class="brand-studio__input" placeholder="Contact">
                </div>
            </div>
            <div>
                <label class="brand-studio__label" for="lede">Short description</label>
                <textarea id="lede" name="lede" rows="3" class="brand-studio__input brand-studio__textarea"
                    placeholder="A friendly line about how you help…">{{ old('lede', $content['lede'] ?? '') }}</textarea>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Message form</p>
                <h2 class="brand-studio__section-title">Form labels</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="form_title">Form heading</label>
                    <input id="form_title" name="form_title" type="text"
                        value="{{ old('form_title', $content['form_title'] ?? '') }}"
                        class="brand-studio__input" placeholder="Send a message">
                </div>
                <div>
                    <label class="brand-studio__label" for="form_button">Button text</label>
                    <input id="form_button" name="form_button" type="text"
                        value="{{ old('form_button', $content['form_button'] ?? '') }}"
                        class="brand-studio__input" placeholder="Send message">
                </div>
            </div>
        </section>

        <div class="website-hub__tip">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">Phone, email & WhatsApp</p>
                <p class="website-hub__tip-text">
                    Update those under
                    <a href="{{ route('admin.settings.index') }}" class="underline underline-offset-2">Settings → General</a>
                    so they stay consistent across Contact, About, and the footer.
                </p>
            </div>
        </div>

        <div class="flex justify-end">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save contact page</x-admin.button>
        </div>
    </form>
</div>
@endsection
