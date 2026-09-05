@php
    use App\Support\TestimonialSources;
    $sources = TestimonialSources::all();
    $selected = old('source', $value ?? '');
@endphp

<div class="mb-4" x-data="{ source: @js($selected ?: '') }">
    <p class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
        Client came from
        <span class="font-normal text-gray-400">(optional)</span>
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
        Pick the social platform — its logo and brand color show on the homepage card.
    </p>

    <input type="hidden" name="source" :value="source">

    <div class="social-source-grid" role="radiogroup" aria-label="Social source">
        <button
            type="button"
            class="social-source-chip"
            :class="{ 'is-on': source === '' }"
            @click="source = ''"
            role="radio"
            :aria-checked="source === ''"
        >
            <span class="social-source-chip__icon social-source-chip__icon--none" aria-hidden="true">
                <i class="fas fa-minus"></i>
            </span>
            <span class="social-source-chip__label">None</span>
        </button>

        @foreach ($sources as $key => $meta)
            <button
                type="button"
                class="social-source-chip"
                :class="{ 'is-on': source === '{{ $key }}' }"
                style="--source-color: {{ $meta['color'] }}; --source-soft: {{ $meta['color_soft'] }};"
                @click="source = '{{ $key }}'"
                role="radio"
                :aria-checked="source === '{{ $key }}'"
            >
                <span class="social-source-chip__icon" aria-hidden="true">
                    <i class="{{ $meta['icon'] }}"></i>
                </span>
                <span class="social-source-chip__label">{{ $meta['label'] }}</span>
            </button>
        @endforeach
    </div>

    @error('source')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <i class="fas fa-exclamation-circle mr-1"></i>
            {{ $message }}
        </p>
    @enderror
</div>

@once
    @push('styles')
        <style>
            .social-source-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.65rem;
            }

            @media (min-width: 640px) {
                .social-source-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            .social-source-chip {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                width: 100%;
                padding: 0.7rem 0.75rem;
                border: 1px solid #e5e5e5;
                border-radius: 12px;
                background: #fff;
                cursor: pointer;
                text-align: left;
                transition:
                    border-color 0.2s ease,
                    background 0.2s ease,
                    box-shadow 0.2s ease,
                    transform 0.2s ease;
            }

            .dark .social-source-chip {
                background: #111827;
                border-color: #374151;
            }

            .social-source-chip:hover {
                border-color: color-mix(in srgb, var(--source-color, #0a0a0a) 45%, #e5e5e5);
                transform: translateY(-1px);
            }

            .social-source-chip.is-on {
                border-color: var(--source-color, #0a0a0a);
                background: var(--source-soft, #fafafa);
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--source-color, #0a0a0a) 18%, transparent);
            }

            .dark .social-source-chip.is-on {
                background: color-mix(in srgb, var(--source-color, #fff) 16%, #111827);
            }

            .social-source-chip__icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.85rem;
                height: 1.85rem;
                border-radius: 999px;
                flex-shrink: 0;
                color: #fff;
                background: var(--source-color, #0a0a0a);
                font-size: 0.85rem;
            }

            .social-source-chip__icon--none {
                background: #ececec;
                color: #6b6b6b;
            }

            .dark .social-source-chip__icon--none {
                background: #374151;
                color: #d1d5db;
            }

            .social-source-chip__label {
                font-size: 0.8rem;
                font-weight: 700;
                letter-spacing: 0.01em;
                color: #0a0a0a;
            }

            .dark .social-source-chip__label {
                color: #f3f4f6;
            }
        </style>
    @endpush
@endonce
