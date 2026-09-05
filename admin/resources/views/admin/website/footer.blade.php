@extends('admin.layouts.app')

@section('title', 'Footer')

@section('content')
    @php
        $trust = old('trust', $content['trust'] ?? []);
        while (count($trust) < 4) {
            $trust[] = '';
        }
        $customerCare = old('customer_care', $content['customer_care'] ?? []);
        $information = old('information', $content['information'] ?? []);
        if (count($customerCare) === 0) {
            $customerCare = [['label' => '', 'href' => '/pages/contact']];
        }
        if (count($information) === 0) {
            $information = [['label' => '', 'href' => '/pages/about']];
        }
    @endphp

    @include('admin.website._shell-start')

    <form action="{{ route('admin.website.update', 'footer') }}" method="POST" class="brand-studio brand-studio--full space-y-6"
        x-data="{
            care: @js($customerCare),
            info: @js($information),
            presets: @js(array_keys($linkPresets)),
            isPreset(href) { return this.presets.includes(href); },
            addCare() { if (this.care.length < 8) this.care.push({ label: '', href: '/pages/contact' }); },
            addInfo() { if (this.info.length < 8) this.info.push({ label: '', href: '/pages/about' }); },
            removeCare(i) { this.care.splice(i, 1); },
            removeInfo(i) { this.info.splice(i, 1); },
        }"
        data-turbo="false">
        @csrf
        @method('PUT')

        <section class="brand-studio__section space-y-5">
            <div>
                <p class="brand-studio__eyebrow">Trust strip</p>
                <h2 class="brand-studio__section-title">Four promises</h2>
                <p class="brand-studio__hint mt-1">Shown as a row above the footer columns.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($trust as $i => $label)
                    <div>
                        <label class="brand-studio__label">Promise {{ $i + 1 }}</label>
                        <input name="trust[]" type="text" class="brand-studio__input"
                            value="{{ $label }}" placeholder="e.g. Authenticity Guaranteed">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="brand-studio__section space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="brand-studio__eyebrow">Column</p>
                    <h2 class="brand-studio__section-title">Customer care links</h2>
                </div>
                <button type="button" class="website-repeater__add" @click="addCare()" :disabled="care.length >= 8">
                    <i class="fas fa-plus"></i> Add link
                </button>
            </div>
            <template x-for="(item, index) in care" :key="'c'+index">
                <div class="website-repeater__item">
                    <div class="website-repeater__head">
                        <p class="website-repeater__badge">Link <span x-text="index + 1"></span></p>
                        <button type="button" class="website-repeater__icon-btn website-repeater__icon-btn--danger" @click="removeCare(index)" :disabled="care.length <= 1">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                        <div>
                            <label class="brand-studio__label">Label</label>
                            <input type="text" class="brand-studio__input" x-model="item.label" :name="`customer_care[${index}][label]`">
                        </div>
                        <div>
                            <label class="brand-studio__label">Goes to</label>
                            <select class="brand-studio__input" x-model="item.href" :name="`customer_care[${index}][href]`">
                                @foreach ($linkPresets as $href => $label)
                                    <option value="{{ $href }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <section class="brand-studio__section space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="brand-studio__eyebrow">Column</p>
                    <h2 class="brand-studio__section-title">Information links</h2>
                </div>
                <button type="button" class="website-repeater__add" @click="addInfo()" :disabled="info.length >= 8">
                    <i class="fas fa-plus"></i> Add link
                </button>
            </div>
            <template x-for="(item, index) in info" :key="'i'+index">
                <div class="website-repeater__item">
                    <div class="website-repeater__head">
                        <p class="website-repeater__badge">Link <span x-text="index + 1"></span></p>
                        <button type="button" class="website-repeater__icon-btn website-repeater__icon-btn--danger" @click="removeInfo(index)" :disabled="info.length <= 1">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                        <div>
                            <label class="brand-studio__label">Label</label>
                            <input type="text" class="brand-studio__input" x-model="item.label" :name="`information[${index}][label]`">
                        </div>
                        <div>
                            <label class="brand-studio__label">Goes to</label>
                            <select class="brand-studio__input" x-model="item.href" :name="`information[${index}][href]`">
                                @foreach ($linkPresets as $href => $label)
                                    <option value="{{ $href }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <div class="flex justify-end">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save footer</x-admin.button>
        </div>
    </form>
</div>
@endsection
