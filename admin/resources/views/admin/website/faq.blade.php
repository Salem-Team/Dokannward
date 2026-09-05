@extends('admin.layouts.app')

@section('title', 'FAQ')

@section('content')
    @php
        $items = old('items', $content['items'] ?? []);
        if (! is_array($items)) {
            $items = [];
        }
    @endphp

    @include('admin.website._shell-start')

    <div class="website-hub__tip mb-6" role="note">
        <i class="fas fa-scale-balanced" aria-hidden="true"></i>
        <div>
            <p class="website-hub__tip-title">Homepage FAQ vs full policies</p>
            <p class="website-hub__tip-text">
                This section is the short Q&amp;A accordion on the homepage (“Our Policies”).
                For full legal documents (Privacy, Terms, Returns, Shipping), edit them under
                <a href="{{ route('admin.policies.index') }}" class="underline underline-offset-2 font-medium">Policies</a>.
            </p>
        </div>
    </div>

    <form action="{{ route('admin.website.update', 'faq') }}" method="POST" class="brand-studio brand-studio--full space-y-6"
        x-data="{
            items: @js($items),
            add() { this.items.push({ q: '', a: '', tag: '' }); },
            remove(index) { this.items.splice(index, 1); },
            move(index, delta) {
                const next = index + delta;
                if (next < 0 || next >= this.items.length) return;
                const copy = this.items.splice(index, 1)[0];
                this.items.splice(next, 0, copy);
            },
        }"
        data-turbo="false">
        @csrf
        @method('PUT')

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Section chrome</p>
                <h2 class="brand-studio__section-title">FAQ heading</h2>
                <p class="brand-studio__hint mt-1">Shown above the Q&amp;A list on the homepage.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="brand-studio__label" for="faq_eyebrow">Small label</label>
                    <input id="faq_eyebrow" name="eyebrow" type="text" maxlength="80"
                        value="{{ old('eyebrow', $content['eyebrow'] ?? 'Support') }}"
                        class="brand-studio__input" placeholder="Support">
                </div>
                <div>
                    <label class="brand-studio__label" for="faq_title">Main title</label>
                    <input id="faq_title" name="title" type="text" maxlength="120"
                        value="{{ old('title', $content['title'] ?? 'Our Policies') }}"
                        class="brand-studio__input" placeholder="Our Policies">
                </div>
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="brand-studio__eyebrow">Help content</p>
                    <h2 class="brand-studio__section-title">Questions & answers</h2>
                    <p class="brand-studio__hint mt-1">Write clear answers. Empty questions are ignored when you save. Optional tags appear as small labels (e.g. Returns).</p>
                </div>
                <button type="button" class="website-repeater__add" @click="add()" :disabled="items.length >= 20">
                    <i class="fas fa-plus"></i> Add question
                </button>
            </div>

            <template x-if="items.length === 0">
                <div class="website-repeater__empty">
                    <p>No questions yet.</p>
                    <button type="button" class="website-repeater__add" @click="add()">
                        <i class="fas fa-plus"></i> Add your first question
                    </button>
                </div>
            </template>

            <template x-for="(item, index) in items" :key="index">
                <div class="website-repeater__item">
                    <div class="website-repeater__head">
                        <p class="website-repeater__badge">Q<span x-text="index + 1"></span></p>
                        <div class="website-repeater__actions">
                            <button type="button" class="website-repeater__icon-btn" @click="move(index, -1)" :disabled="index === 0" title="Move up">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="website-repeater__icon-btn" @click="move(index, 1)" :disabled="index === items.length - 1" title="Move down">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" class="website-repeater__icon-btn website-repeater__icon-btn--danger" @click="remove(index)" title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-4 mt-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="brand-studio__label">Question</label>
                                <input type="text" class="brand-studio__input" x-model="item.q"
                                    :name="`items[${index}][q]`" placeholder="e.g. What is the return policy?">
                            </div>
                            <div>
                                <label class="brand-studio__label">Tag <span class="opacity-50">(optional)</span></label>
                                <input type="text" class="brand-studio__input" x-model="item.tag"
                                    :name="`items[${index}][tag]`" placeholder="Returns">
                            </div>
                        </div>
                        <div>
                            <label class="brand-studio__label">Answer</label>
                            <textarea rows="3" class="brand-studio__input brand-studio__textarea" x-model="item.a"
                                :name="`items[${index}][a]`" placeholder="Write a helpful answer…"></textarea>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <div class="flex justify-end">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save FAQ</x-admin.button>
        </div>
    </form>
</div>
@endsection
