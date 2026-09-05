@php
    $titles = [
        'home' => 'Homepage',
        'about' => 'About page',
        'contact' => 'Contact page',
        'nav' => 'Menu links',
        'faq' => 'FAQ',
        'footer' => 'Footer',
    ];
    $subs = [
        'home' => 'Edit the titles customers see above product grids and testimonials.',
        'about' => 'Every section of the About page — write in plain language, no code needed.',
        'contact' => 'Headlines and form wording. Contact numbers stay in Settings.',
        'nav' => 'Control the links in the header and mobile menu.',
        'faq' => 'Common questions — used for help content and search engines.',
        'footer' => 'Trust badges and link columns at the bottom of every page.',
    ];
    $previews = [
        'home' => '/',
        'about' => '/pages/about',
        'contact' => '/pages/contact',
        'nav' => '/',
        'faq' => '/',
        'footer' => '/',
    ];
@endphp

<div class="brand-studio-page space-y-6">
    <div class="brand-studio-page__header">
        <div>
            <p class="brand-studio-page__eyebrow">
                <a href="{{ route('admin.website.index') }}" class="hover:underline">Website</a>
                · Edit
            </p>
            <h1 class="brand-studio-page__title">{{ $titles[$section] ?? 'Edit' }}</h1>
            <p class="brand-studio-page__sub">{{ $subs[$section] ?? '' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.website.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper transition-colors">
                <i class="fas fa-arrow-left text-xs opacity-60"></i> All pages
            </a>
            @if (!empty($storefrontBase) && isset($previews[$section]))
                <a href="{{ $storefrontBase }}{{ $previews[$section] }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zibra-line bg-white dark:bg-gray-800 text-sm font-semibold text-zibra-ink dark:text-white hover:bg-zibra-paper transition-colors">
                    Preview <i class="fas fa-external-link-alt text-xs opacity-60"></i>
                </a>
            @endif
        </div>
    </div>
