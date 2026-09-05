@extends('admin.layouts.app')

@section('title', 'Website')

@section('content')
    @php
        $cards = [
            [
                'section' => 'home',
                'icon' => 'fas fa-home',
                'title' => 'Homepage',
                'text' => 'Section titles for new arrivals and customer stories.',
                'hint' => 'Banners & collections are edited separately',
                'tone' => 'ink',
            ],
            [
                'section' => 'about',
                'icon' => 'fas fa-book-open',
                'title' => 'About page',
                'text' => 'Hero, story, quote, principles, and journey — all in plain language.',
                'hint' => 'Shows at /pages/about',
                'tone' => 'warm',
            ],
            [
                'section' => 'contact',
                'icon' => 'fas fa-envelope-open-text',
                'title' => 'Contact page',
                'text' => 'Headlines and form labels. Phone & email stay in Settings.',
                'hint' => 'Shows at /pages/contact',
                'tone' => 'cool',
            ],
            [
                'section' => 'nav',
                'icon' => 'fas fa-bars',
                'title' => 'Menu links',
                'text' => 'What appears in the top navigation and mobile menu.',
                'hint' => 'Drag order with ↑ ↓ buttons',
                'tone' => 'ink',
            ],
            [
                'section' => 'faq',
                'icon' => 'fas fa-circle-question',
                'title' => 'FAQ',
                'text' => 'Questions & answers used for search engines and help content.',
                'hint' => 'Add, edit, or remove anytime',
                'tone' => 'warm',
            ],
            [
                'section' => 'footer',
                'icon' => 'fas fa-shoe-prints',
                'title' => 'Footer',
                'text' => 'Trust badges and the link columns at the bottom of every page.',
                'hint' => 'Contact details stay in Settings',
                'tone' => 'cool',
            ],
        ];
    @endphp

    <div class="brand-studio-page website-hub space-y-8">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Content studio</p>
                <h1 class="brand-studio-page__title">Edit your website</h1>
                <p class="brand-studio-page__sub">
                    Change text and links without code. Pick a page, edit the fields, and save —
                    the live site updates automatically.
                </p>
            </div>
            @if ($storefrontBase)
                <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper dark:hover:bg-gray-700 transition-colors">
                    View live site <i class="fas fa-external-link-alt text-xs opacity-60"></i>
                </a>
            @endif
        </div>

        <div class="website-hub__tip" role="note">
            <i class="fas fa-lightbulb" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">Tip for easy editing</p>
                <p class="website-hub__tip-text">
                    Write like you talk. Leave a field blank only if you want to hide that line.
                    Phone, email, WhatsApp, and address are under <a href="{{ route('admin.settings.index') }}" class="underline underline-offset-2">Settings</a>.
                </p>
            </div>
        </div>

        <div class="website-hub__grid">
            @foreach ($cards as $card)
                <a href="{{ route('admin.website.edit', $card['section']) }}"
                    class="website-hub__card website-hub__card--{{ $card['tone'] }}">
                    <span class="website-hub__card-icon" aria-hidden="true">
                        <i class="{{ $card['icon'] }}"></i>
                    </span>
                    <span class="website-hub__card-body">
                        <span class="website-hub__card-title">{{ $card['title'] }}</span>
                        <span class="website-hub__card-text">{{ $card['text'] }}</span>
                        <span class="website-hub__card-hint">{{ $card['hint'] }}</span>
                    </span>
                    <span class="website-hub__card-cta">
                        Edit <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="website-hub__more">
            <h2 class="website-hub__more-title">Also on your website</h2>
            <div class="website-hub__more-grid">
                <a href="{{ route('admin.banners.index') }}" class="website-hub__more-link">
                    <i class="fas fa-image"></i>
                    <span>
                        <strong>Homepage banners</strong>
                        <small>Full-bleed CTA plates under the hero</small>
                    </span>
                </a>
                <a href="{{ route('admin.testimonials.index') }}" class="website-hub__more-link">
                    <i class="fas fa-comment-dots"></i>
                    <span>
                        <strong>Customer stories</strong>
                        <small>Names, quotes, and ratings</small>
                    </span>
                </a>
                <a href="{{ route('admin.policies.index') }}" class="website-hub__more-link">
                    <i class="fas fa-scale-balanced"></i>
                    <span>
                        <strong>Store policies</strong>
                        <small>Privacy, Terms, Returns &amp; Shipping</small>
                    </span>
                </a>
                <a href="{{ route('admin.pages.index') }}" class="website-hub__more-link">
                    <i class="fas fa-file-alt"></i>
                    <span>
                        <strong>Other text pages</strong>
                        <small>{{ $publishedPages }}/{{ $pagesCount }} published · custom long-form pages</small>
                    </span>
                </a>
                <a href="{{ route('admin.collections.index') }}" class="website-hub__more-link">
                    <i class="fas fa-layer-group"></i>
                    <span>
                        <strong>Collections</strong>
                        <small>Homepage collection plates & covers</small>
                    </span>
                </a>
            </div>
        </div>
    </div>
@endsection
