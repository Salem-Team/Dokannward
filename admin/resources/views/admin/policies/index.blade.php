@extends('admin.layouts.app')

@section('title', 'Policies')

@section('content')
    <div class="brand-studio-page website-hub space-y-8">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Legal &amp; help</p>
                <h1 class="brand-studio-page__title">Store policies</h1>
                <p class="brand-studio-page__sub">
                    Edit Privacy, Terms, Returns, and Shipping in one place.
                    Changes publish to the live website automatically.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.website.edit', 'faq') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-circle-question text-xs opacity-60"></i>
                    Homepage FAQ
                    <span class="opacity-50 text-xs font-normal">({{ $faqCount }})</span>
                </a>
                @if ($storefrontBase)
                    <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper dark:hover:bg-gray-700 transition-colors">
                        View live site <i class="fas fa-external-link-alt text-xs opacity-60"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="website-hub__tip" role="note">
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">How this works</p>
                <p class="website-hub__tip-text">
                    Each card opens a rich editor — use headings, lists, and links like a document.
                    Keep policies clear and up to date; customers see them under
                    <code class="text-xs">/policies/…</code> and in the footer.
                    Short homepage Q&amp;A lives in
                    <a href="{{ route('admin.website.edit', 'faq') }}" class="underline underline-offset-2">Website → FAQ</a>.
                </p>
            </div>
        </div>

        <div class="website-hub__grid">
            @foreach ($cards as $card)
                @php
                    $statusLabel = $card['published'] ? 'Published' : 'Draft';
                    $statusTone = $card['published'] ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-amber-800 bg-amber-50 border-amber-200';
                @endphp
                <a href="{{ route('admin.policies.edit', $card['slug']) }}"
                    class="website-hub__card website-hub__card--{{ $card['tone'] }}">
                    <span class="website-hub__card-icon" aria-hidden="true">
                        <i class="{{ $card['icon'] }}"></i>
                    </span>
                    <span class="website-hub__card-body">
                        <span class="website-hub__card-title">{{ $card['title'] }}</span>
                        <span class="website-hub__card-text">{{ $card['description'] }}</span>
                        <span class="website-hub__card-hint flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase {{ $statusTone }}">
                                {{ $statusLabel }}
                            </span>
                            <span>{{ number_format($card['word_count']) }} words</span>
                            <span class="opacity-40">·</span>
                            <span>{{ $card['path'] }}</span>
                        </span>
                    </span>
                    <span class="website-hub__card-cta">
                        Edit <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="website-hub__more">
            <h2 class="website-hub__more-title">Related</h2>
            <div class="website-hub__more-grid">
                <a href="{{ route('admin.website.edit', 'faq') }}" class="website-hub__more-link">
                    <i class="fas fa-circle-question"></i>
                    <span>
                        <strong>Homepage FAQ</strong>
                        <small>“Our Policies” accordion on the home page</small>
                    </span>
                </a>
                <a href="{{ route('admin.website.edit', 'footer') }}" class="website-hub__more-link">
                    <i class="fas fa-shoe-prints"></i>
                    <span>
                        <strong>Footer links</strong>
                        <small>Point customers to these policy pages</small>
                    </span>
                </a>
                <a href="{{ route('admin.pages.index') }}" class="website-hub__more-link">
                    <i class="fas fa-file-alt"></i>
                    <span>
                        <strong>Other text pages</strong>
                        <small>Custom long-form pages beyond legal policies</small>
                    </span>
                </a>
                <a href="{{ route('admin.settings.index') }}" class="website-hub__more-link">
                    <i class="fas fa-cog"></i>
                    <span>
                        <strong>Store settings</strong>
                        <small>Email &amp; phone used in policy contact lines</small>
                    </span>
                </a>
            </div>
        </div>
    </div>
@endsection
