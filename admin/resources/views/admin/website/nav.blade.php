@extends('admin.layouts.app')

@section('title', 'Menu links')

@section('content')
    @php
        $items = old('items', $content['items'] ?? []);
        if (! is_array($items) || count($items) === 0) {
            $items = [['label' => '', 'href' => '/']];
        }
        $presetKeys = array_keys($linkPresets);
        $editorItems = collect($items)->map(function ($row) use ($presetKeys) {
            $href = $row['href'] ?? '/';
            $isPreset = in_array($href, $presetKeys, true);

            return [
                'label' => $row['label'] ?? '',
                'preset' => $isPreset ? $href : '__custom__',
                'custom' => $isPreset ? '' : $href,
            ];
        })->values()->all();
    @endphp

    @include('admin.website._shell-start')

    <form action="{{ route('admin.website.update', 'nav') }}" method="POST" class="brand-studio brand-studio--full space-y-6"
        x-data="{
            presets: @js($presetKeys),
            items: @js($editorItems),
            resolvedHref(item) {
                return item.preset === '__custom__' ? (item.custom || '/') : item.preset;
            },
            onPresetChange(item) {
                if (item.preset !== '__custom__') item.custom = '';
            },
            add() {
                this.items.push({ label: '', preset: '/', custom: '' });
            },
            remove(index) {
                this.items.splice(index, 1);
            },
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
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="brand-studio__eyebrow">Navigation</p>
                    <h2 class="brand-studio__section-title">Menu items</h2>
                    <p class="brand-studio__hint mt-1">Shown in the desktop header and the mobile drawer. Keep it short (4–7 links works best).</p>
                </div>
                <button type="button" class="website-repeater__add" @click="add()" :disabled="items.length >= 12">
                    <i class="fas fa-plus"></i> Add link
                </button>
            </div>

            <template x-for="(item, index) in items" :key="index">
                <div class="website-repeater__item">
                    <div class="website-repeater__head">
                        <p class="website-repeater__badge">Link <span x-text="index + 1"></span></p>
                        <div class="website-repeater__actions">
                            <button type="button" class="website-repeater__icon-btn" @click="move(index, -1)" :disabled="index === 0" title="Move up">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="website-repeater__icon-btn" @click="move(index, 1)" :disabled="index === items.length - 1" title="Move down">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" class="website-repeater__icon-btn website-repeater__icon-btn--danger" @click="remove(index)" :disabled="items.length <= 1" title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                        <div>
                            <label class="brand-studio__label">Link name</label>
                            <input type="text" class="brand-studio__input" x-model="item.label"
                                :name="`items[${index}][label]`" placeholder="e.g. About" required>
                        </div>
                        <div>
                            <label class="brand-studio__label">Goes to</label>
                            <select class="brand-studio__input" x-model="item.preset" @change="onPresetChange(item)">
                                @foreach ($linkPresets as $href => $label)
                                    <option value="{{ $href }}">{{ $label }}</option>
                                @endforeach
                                <option value="__custom__">Custom address…</option>
                            </select>
                            <input type="text" class="brand-studio__input mt-2"
                                x-show="item.preset === '__custom__'" x-cloak
                                x-model="item.custom"
                                placeholder="/your-page or https://…">
                            <input type="hidden" :name="`items[${index}][href]`" :value="resolvedHref(item)">
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <div class="flex justify-end">
            <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save menu</x-admin.button>
        </div>
    </form>
</div>
@endsection
