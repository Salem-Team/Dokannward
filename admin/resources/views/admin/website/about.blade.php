@extends('admin.layouts.app')

@section('title', 'About page')

@section('content')
    @php
        $paragraphs = old('story_paragraphs_text');
        if ($paragraphs === null) {
            $paragraphs = implode("\n\n", $content['story_paragraphs'] ?? []);
        }
        $pillars = old('pillars', $content['pillars'] ?? []);
        $journey = old('journey', $content['journey'] ?? []);
        while (count($pillars) < 3) {
            $pillars[] = ['title' => '', 'text' => ''];
        }
        while (count($journey) < 3) {
            $journey[] = ['title' => '', 'text' => ''];
        }
    @endphp

    @include('admin.website._shell-start')

    <form action="{{ route('admin.website.update', 'about') }}" method="POST" class="brand-studio brand-studio--full space-y-6" data-turbo="false">
        @csrf
        @method('PUT')

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">1 · Top of page</p>
                <h2 class="brand-studio__section-title">Hero</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="hero_eyebrow">Small label</label>
                    <input id="hero_eyebrow" name="hero_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('hero_eyebrow', $content['hero_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="hero_image">Background image path or URL</label>
                    <input id="hero_image" name="hero_image" type="text" class="brand-studio__input"
                        value="{{ old('hero_image', $content['hero_image'] ?? '') }}"
                        placeholder="/images/…">
                </div>
            </div>
            <div>
                <label class="brand-studio__label" for="hero_title">Big headline</label>
                <textarea id="hero_title" name="hero_title" rows="2" class="brand-studio__input brand-studio__textarea"
                    placeholder="Use a new line for a second line">{{ old('hero_title', $content['hero_title'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="brand-studio__label" for="hero_subtitle">Supporting sentence</label>
                <textarea id="hero_subtitle" name="hero_subtitle" rows="2" class="brand-studio__input brand-studio__textarea">{{ old('hero_subtitle', $content['hero_subtitle'] ?? '') }}</textarea>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">2 · Our story</p>
                <h2 class="brand-studio__section-title">Story block</h2>
                <p class="brand-studio__hint mt-1">Separate paragraphs with a blank line.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="story_eyebrow">Small label</label>
                    <input id="story_eyebrow" name="story_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('story_eyebrow', $content['story_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="story_title">Section title</label>
                    <input id="story_title" name="story_title" type="text" class="brand-studio__input"
                        value="{{ old('story_title', $content['story_title'] ?? '') }}">
                </div>
            </div>
            <div>
                <label class="brand-studio__label" for="story_image">Side image / logo path</label>
                <input id="story_image" name="story_image" type="text" class="brand-studio__input"
                    value="{{ old('story_image', $content['story_image'] ?? '') }}"
                    placeholder="/images/dokan-ward-logo.svg">
                <p class="brand-studio__hint mt-2">Prefer the SVG logo so it blends with the page background.</p>
            </div>
            <div>
                <label class="brand-studio__label" for="story_paragraphs_text">Story paragraphs</label>
                <textarea id="story_paragraphs_text" name="story_paragraphs_text" rows="10"
                    class="brand-studio__input brand-studio__textarea">{{ $paragraphs }}</textarea>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">3 · Quote band</p>
                <h2 class="brand-studio__section-title">Featured quote</h2>
            </div>
            <div>
                <label class="brand-studio__label" for="quote">Quote</label>
                <textarea id="quote" name="quote" rows="3" class="brand-studio__input brand-studio__textarea">{{ old('quote', $content['quote'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="brand-studio__label" for="quote_attribution">Attribution</label>
                <input id="quote_attribution" name="quote_attribution" type="text" class="brand-studio__input"
                    value="{{ old('quote_attribution', $content['quote_attribution'] ?? '') }}"
                    placeholder="— Dokan Ward">
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">4 · Principles</p>
                <h2 class="brand-studio__section-title">What we stand for</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="pillars_eyebrow">Small label</label>
                    <input id="pillars_eyebrow" name="pillars_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('pillars_eyebrow', $content['pillars_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="pillars_title">Section title</label>
                    <input id="pillars_title" name="pillars_title" type="text" class="brand-studio__input"
                        value="{{ old('pillars_title', $content['pillars_title'] ?? '') }}">
                </div>
            </div>
            <div class="space-y-4">
                @foreach ($pillars as $i => $pillar)
                    <div class="website-repeater__item">
                        <p class="website-repeater__badge">Principle {{ $i + 1 }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label class="brand-studio__label">Title</label>
                                <input name="pillars[{{ $i }}][title]" type="text" class="brand-studio__input"
                                    value="{{ $pillar['title'] ?? '' }}">
                            </div>
                            <div>
                                <label class="brand-studio__label">Short text</label>
                                <input name="pillars[{{ $i }}][text]" type="text" class="brand-studio__input"
                                    value="{{ $pillar['text'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">5 · Journey</p>
                <h2 class="brand-studio__section-title">Process steps</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="journey_eyebrow">Small label</label>
                    <input id="journey_eyebrow" name="journey_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('journey_eyebrow', $content['journey_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="journey_title">Section title</label>
                    <input id="journey_title" name="journey_title" type="text" class="brand-studio__input"
                        value="{{ old('journey_title', $content['journey_title'] ?? '') }}">
                </div>
            </div>
            <div class="space-y-4">
                @foreach ($journey as $i => $step)
                    <div class="website-repeater__item">
                        <p class="website-repeater__badge">Step {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label class="brand-studio__label">Title</label>
                                <input name="journey[{{ $i }}][title]" type="text" class="brand-studio__input"
                                    value="{{ $step['title'] ?? '' }}">
                            </div>
                            <div>
                                <label class="brand-studio__label">Short text</label>
                                <input name="journey[{{ $i }}][text]" type="text" class="brand-studio__input"
                                    value="{{ $step['text'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">6 · Brands callout</p>
                <h2 class="brand-studio__section-title">The edit</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="edit_eyebrow">Small label</label>
                    <input id="edit_eyebrow" name="edit_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('edit_eyebrow', $content['edit_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="edit_image">Image path or URL</label>
                    <input id="edit_image" name="edit_image" type="text" class="brand-studio__input"
                        value="{{ old('edit_image', $content['edit_image'] ?? '') }}">
                </div>
            </div>
            <div>
                <label class="brand-studio__label" for="edit_title">Headline</label>
                <textarea id="edit_title" name="edit_title" rows="2" class="brand-studio__input brand-studio__textarea">{{ old('edit_title', $content['edit_title'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="brand-studio__label" for="edit_text">Supporting text</label>
                <textarea id="edit_text" name="edit_text" rows="3" class="brand-studio__input brand-studio__textarea">{{ old('edit_text', $content['edit_text'] ?? '') }}</textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="edit_button_label">Button text</label>
                    <input id="edit_button_label" name="edit_button_label" type="text" class="brand-studio__input"
                        value="{{ old('edit_button_label', $content['edit_button_label'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="edit_button_href">Button goes to</label>
                    <select id="edit_button_href" name="edit_button_href" class="brand-studio__input">
                        @foreach ($linkPresets as $href => $label)
                            <option value="{{ $href }}" @selected(old('edit_button_href', $content['edit_button_href'] ?? '') === $href)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">7 · Bottom CTA</p>
                <h2 class="brand-studio__section-title">Visit & connect</h2>
                <p class="brand-studio__hint mt-1">Address comes from Settings. Only the labels are edited here.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="brand-studio__label" for="cta_eyebrow">Small label</label>
                    <input id="cta_eyebrow" name="cta_eyebrow" type="text" class="brand-studio__input"
                        value="{{ old('cta_eyebrow', $content['cta_eyebrow'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="cta_primary_label">Primary button</label>
                    <input id="cta_primary_label" name="cta_primary_label" type="text" class="brand-studio__input"
                        value="{{ old('cta_primary_label', $content['cta_primary_label'] ?? '') }}">
                </div>
                <div>
                    <label class="brand-studio__label" for="cta_secondary_label">Secondary button</label>
                    <input id="cta_secondary_label" name="cta_secondary_label" type="text" class="brand-studio__input"
                        value="{{ old('cta_secondary_label', $content['cta_secondary_label'] ?? '') }}">
                </div>
            </div>
        </section>

        <div class="flex justify-end sticky bottom-4 z-10">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save About page</x-admin.button>
        </div>
    </form>
</div>
@endsection
