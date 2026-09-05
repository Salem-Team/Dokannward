@props([
    'label' => 'Rating',
    'name' => 'rating',
    'value' => 5,
    'required' => false,
    'error' => null,
    'helpText' => null,
    'max' => 5,
])

@php
    $current = (int) old($name, $value ?: 5);
    if ($current < 1 || $current > (int) $max) {
        $current = (int) $max;
    }
    $labels = [
        1 => 'Poor',
        2 => 'Fair',
        3 => 'Good',
        4 => 'Very good',
        5 => 'Excellent',
    ];
@endphp

<div
    class="admin-star-rating mb-4"
    x-data="{
        rating: {{ $current }},
        hover: 0,
        labels: @js($labels),
        get active() { return this.hover || this.rating; },
        get label() { return this.labels[this.active] || ''; },
        set(value) { this.rating = value; },
    }"
>
    @if ($label)
        <div class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </div>
    @endif

    {{-- Static value so the form always submits even before Alpine hydrates.
         Never put HTML `required` on a hidden field (browsers block submit silently). --}}
    <input type="hidden" name="{{ $name }}" value="{{ $current }}" x-model.number="rating">

    <div
        class="admin-star-rating__panel {{ $error ? 'admin-star-rating__panel--error' : '' }}"
        role="radiogroup"
        aria-label="{{ $label }}"
    >
        <div class="admin-star-rating__stars" @mouseleave="hover = 0">
            @for ($i = 1; $i <= $max; $i++)
                <button
                    type="button"
                    class="admin-star-rating__star"
                    role="radio"
                    :aria-checked="rating === {{ $i }}"
                    aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }} — {{ $labels[$i] ?? '' }}"
                    :class="{ 'is-on': active >= {{ $i }}, 'is-selected': rating === {{ $i }} }"
                    @mouseenter="hover = {{ $i }}"
                    @focus="hover = {{ $i }}"
                    @click="set({{ $i }})"
                    @keydown.arrow-right.prevent="set(Math.min({{ $max }}, rating + 1))"
                    @keydown.arrow-left.prevent="set(Math.max(1, rating - 1))"
                >
                    <i class="fas fa-star" aria-hidden="true"></i>
                </button>
            @endfor
        </div>

        <div class="admin-star-rating__meta">
            <span class="admin-star-rating__score" x-text="active"></span>
            <span class="admin-star-rating__sep" aria-hidden="true">·</span>
            <span class="admin-star-rating__caption" x-text="label"></span>
        </div>
    </div>

    @if ($error)
        <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <i class="fas fa-exclamation-circle mr-1"></i>
            {{ $error }}
        </p>
    @endif

    @if ($helpText && ! $error)
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $helpText }}</p>
    @endif
</div>

@once
    @push('styles')
        <style>
            .admin-star-rating__panel {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.85rem 1.15rem;
                min-height: 3.25rem;
                padding: 0.85rem 1rem;
                border: 1px solid #e5e5e5;
                border-radius: 12px;
                background: #fafafa;
                transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            }

            .dark .admin-star-rating__panel {
                border-color: #374151;
                background: #111827;
            }

            .admin-star-rating__panel:focus-within {
                border-color: #0a0a0a;
                box-shadow: 0 0 0 3px rgb(10 10 10 / 0.08);
                background: #fff;
            }

            .dark .admin-star-rating__panel:focus-within {
                border-color: #f3f4f6;
                box-shadow: 0 0 0 3px rgb(255 255 255 / 0.08);
                background: #0b1220;
            }

            .admin-star-rating__panel--error {
                border-color: #ef4444;
                background: #fef2f2;
            }

            .admin-star-rating__stars {
                display: inline-flex;
                align-items: center;
                gap: 0.2rem;
            }

            .admin-star-rating__star {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.35rem;
                height: 2.35rem;
                padding: 0;
                border: 0;
                border-radius: 999px;
                background: transparent;
                color: #d4d4d4;
                cursor: pointer;
                transition:
                    color 0.2s ease,
                    transform 0.25s cubic-bezier(0.16, 1, 0.3, 1),
                    background 0.2s ease;
            }

            .dark .admin-star-rating__star {
                color: #4b5563;
            }

            .admin-star-rating__star i {
                font-size: 1.2rem;
                line-height: 1;
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .admin-star-rating__star:hover,
            .admin-star-rating__star:focus-visible {
                background: rgb(10 10 10 / 0.05);
                outline: none;
            }

            .dark .admin-star-rating__star:hover,
            .dark .admin-star-rating__star:focus-visible {
                background: rgb(255 255 255 / 0.06);
            }

            .admin-star-rating__star.is-on {
                color: #0a0a0a;
            }

            .dark .admin-star-rating__star.is-on {
                color: #f9fafb;
            }

            .admin-star-rating__star.is-on i {
                transform: scale(1.06);
            }

            .admin-star-rating__star.is-selected {
                background: rgb(10 10 10 / 0.06);
            }

            .dark .admin-star-rating__star.is-selected {
                background: rgb(255 255 255 / 0.08);
            }

            .admin-star-rating__meta {
                display: inline-flex;
                align-items: baseline;
                gap: 0.4rem;
                min-width: 7.5rem;
                font-size: 0.8125rem;
                letter-spacing: 0.02em;
                color: #6b6b6b;
            }

            .dark .admin-star-rating__meta {
                color: #9ca3af;
            }

            .admin-star-rating__score {
                font-size: 1.05rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                color: #0a0a0a;
                font-variant-numeric: tabular-nums;
            }

            .dark .admin-star-rating__score {
                color: #f9fafb;
            }

            .admin-star-rating__sep {
                opacity: 0.35;
            }

            .admin-star-rating__caption {
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.12em;
                font-size: 0.68rem;
            }

            @media (prefers-reduced-motion: reduce) {
                .admin-star-rating__star,
                .admin-star-rating__star i {
                    transition: none;
                }
            }
        </style>
    @endpush
@endonce
