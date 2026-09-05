@extends('admin.layouts.app')

@section('title', 'Banners')

@section('content')
    @php
        $total = $banners->total();
        $scheduledCount = $scheduledCount ?? 0;
        $expiredCount = $expiredCount ?? 0;
        $offCount = $offCount ?? 0;
    @endphp

    <div class="brand-studio-page banner-desk"
        x-data="{
            filter: 'all',
            query: '',
            matches(el) {
                const q = this.query.trim().toLowerCase();
                const title = (el.dataset.title || '').toLowerCase();
                const subtitle = (el.dataset.subtitle || '').toLowerCase();
                const cta = (el.dataset.cta || '').toLowerCase();
                const status = el.dataset.status || '';
                const textOk = !q || title.includes(q) || subtitle.includes(q) || cta.includes(q);
                const statusOk = this.filter === 'all' || this.filter === status;
                return textOk && statusOk;
            },
            get visibleCount() {
                const cards = this.$refs.grid
                    ? Array.from(this.$refs.grid.querySelectorAll('.banner-desk__card'))
                    : [];
                return cards.filter((el) => this.matches(el)).length;
            },
        }">

        <header class="banner-desk__hero">
            <div class="banner-desk__hero-copy">
                <p class="brand-studio-page__eyebrow">Website · Content</p>
                <h1 class="brand-studio-page__title">Banners</h1>
                <p class="banner-desk__lede">
                    Edit the homepage CTA plates shown on dokannward.com
                    (image, title, subtitle, button). Live rows sync through
                    <code>/api/banners</code> → slots 0 &amp; 1.
                </p>
            </div>
            <div class="banner-desk__hero-actions">
                @if (! empty($storefrontBase))
                    <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener"
                        class="banner-desk__ghost-btn">
                        View homepage <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                    </a>
                @endif
                <a href="{{ route('admin.banners.create') }}" class="banner-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add Banner
                </a>
            </div>
        </header>
<section class="banner-desk__kpis" aria-label="Banner summary">
            <article class="banner-desk__kpi">
                <p class="banner-desk__kpi-label">Total</p>
                <p class="banner-desk__kpi-value">{{ $total }}</p>
            </article>
            <article class="banner-desk__kpi banner-desk__kpi--live">
                <p class="banner-desk__kpi-label">Live</p>
                <p class="banner-desk__kpi-value">{{ $liveCount }}</p>
            </article>
            <article class="banner-desk__kpi">
                <p class="banner-desk__kpi-label">Scheduled</p>
                <p class="banner-desk__kpi-value">{{ $scheduledCount }}</p>
            </article>
            <article class="banner-desk__kpi">
                <p class="banner-desk__kpi-label">Expired</p>
                <p class="banner-desk__kpi-value">{{ $expiredCount }}</p>
            </article>
            <article class="banner-desk__kpi banner-desk__kpi--accent">
                <p class="banner-desk__kpi-label">Off / Hidden</p>
                <p class="banner-desk__kpi-value">{{ $offCount }}</p>
                <p class="banner-desk__kpi-hint">Slots 0–1 fill first</p>
            </article>
        </section>

        <div class="banner-integration banner-integration--index banner-desk__map">
            <div class="banner-integration__copy">
                <p class="banner-integration__eyebrow">Integration map</p>
                <p class="banner-integration__text mb-0">
                    <strong>{{ $liveCount }}</strong> live now on the website
                    · Position <strong>0</strong> primary · Position <strong>1</strong> secondary
                    · Higher positions fill only when earlier live banners are missing
                </p>
            </div>
            <div class="banner-integration__slots" aria-hidden="true">
                <span class="banner-integration__slot is-primary">API → Homepage</span>
                <span class="banner-integration__slot is-secondary">Auto cache purge</span>
            </div>
        </div>

        @if ($banners->isEmpty())
            <div class="banner-desk__empty">
                <div class="banner-desk__empty-plates" aria-hidden="true">
                    <span></span><span></span>
                </div>
                <p class="banner-desk__empty-eyebrow">Content</p>
                <h2 class="banner-desk__empty-title">No banners yet</h2>
                <p class="banner-desk__empty-text">
                    Until you add live banners, the homepage uses built-in editorial fallbacks.
                    Create a banner with a cover, title, and CTA to take over those plates.
                </p>
                <a href="{{ route('admin.banners.create') }}" class="banner-desk__primary-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add Banner
                </a>
            </div>
        @else
            <div class="banner-desk__toolbar">
                <div class="banner-desk__filters" role="tablist" aria-label="Filter by status">
                    @foreach ([
                        'all' => ['All', $total],
                        'live' => ['Live', $liveCount],
                        'scheduled' => ['Scheduled', $scheduledCount],
                        'expired' => ['Expired', $expiredCount],
                        'off' => ['Hidden', $offCount],
                    ] as $key => [$label, $count])
                        <button type="button" role="tab"
                            class="banner-desk__filter"
                            :class="filter === '{{ $key }}' && 'is-active'"
                            @click="filter = '{{ $key }}'"
                            :aria-selected="filter === '{{ $key }}'">
                            {{ $label }} <em>{{ $count }}</em>
                        </button>
                    @endforeach
                </div>
                <label class="banner-desk__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" x-model="query" placeholder="Search title, subtitle, CTA…" autocomplete="off">
                </label>
            </div>

            <div class="banner-desk__grid" x-ref="grid">
                @foreach ($banners as $index => $banner)
                    @php
                        $status = $banner->liveStatus();
                        $statusLabel = match ($status) {
                            'live' => 'Live · On site',
                            'scheduled' => 'Scheduled',
                            'expired' => 'Expired',
                            default => 'Off · Hidden',
                        };
                        $slot = $banner->homepageSlotHint();
                        $cta = $banner->button_text ?: 'Shop now';
                        $href = $banner->button_url ?: '/collections/all';
                    @endphp
                    <article
                        class="banner-desk__card{{ $status === 'live' ? ' is-live' : ' is-quiet' }}"
                        style="--bd-i: {{ $index }};"
                        data-status="{{ $status }}"
                        data-title="{{ $banner->title }}"
                        data-subtitle="{{ $banner->subtitle }}"
                        data-cta="{{ $cta }}"
                        x-show="matches($el)"
                        x-transition.opacity.duration.150ms>

                        <a href="{{ route('admin.banners.edit', $banner->id) }}"
                            class="banner-desk__cover"
                            aria-label="Edit {{ $banner->title ?: 'banner' }}">
                            @if ($banner->image_url)
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?: 'Banner' }}" loading="lazy">
                            @else
                                <span class="banner-desk__cover-fallback">
                                    <span class="banner-desk__cover-kicker">CTA plate</span>
                                    <span class="banner-desk__cover-title">{{ $banner->title ?: 'Untitled banner' }}</span>
                                </span>
                            @endif
                            <span class="banner-desk__cover-veil" aria-hidden="true"></span>
                            <span class="banner-desk__status banner-desk__status--{{ $status }}">
                                <span class="banner-desk__status-dot" aria-hidden="true"></span>
                                {{ $statusLabel }}
                            </span>
                            <span class="banner-desk__cover-cta">
                                Open studio <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </span>
                        </a>

                        <div class="banner-desk__body">
                            <p class="banner-desk__slot">{{ $slot }}</p>
                            <h2 class="banner-desk__name">
                                <a href="{{ route('admin.banners.edit', $banner->id) }}">
                                    {{ $banner->title ?: 'Untitled banner' }}
                                </a>
                            </h2>
                            @if ($banner->subtitle)
                                <p class="banner-desk__desc">{{ \Illuminate\Support\Str::limit($banner->subtitle, 120) }}</p>
                            @endif

                            <div class="banner-desk__cta-preview">
                                <span class="banner-desk__cta-label">Button</span>
                                <span class="banner-desk__cta-chip">{{ $cta }}</span>
                                <code class="banner-desk__cta-href">{{ \Illuminate\Support\Str::limit($href, 42) }}</code>
                            </div>

                            <dl class="banner-desk__meta">
                                <div>
                                    <dt>Position</dt>
                                    <dd>{{ $banner->position }}</dd>
                                </div>
                                <div>
                                    <dt>Starts</dt>
                                    <dd>{{ $banner->start_at ? $banner->start_at->format('M d, Y') : 'Always' }}</dd>
                                </div>
                                <div>
                                    <dt>Ends</dt>
                                    <dd>{{ $banner->end_at ? $banner->end_at->format('M d, Y') : 'Open' }}</dd>
                                </div>
                            </dl>

                            <div class="banner-desk__actions">
                                <a href="{{ route('admin.banners.edit', $banner->id) }}"
                                    class="banner-desk__action is-primary">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    Edit
                                </a>
                                @if (! empty($storefrontBase) && $status === 'live')
                                    <a href="{{ $storefrontBase }}/" target="_blank" rel="noopener"
                                        class="banner-desk__action">
                                        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                        Live
                                    </a>
                                @endif
                                <form action="{{ route('admin.banners.destroy', $banner->id) }}"
                                    method="POST"
                                    class="banner-desk__delete"
                                    data-confirm="Delete this banner?" data-confirm-title="Delete banner?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="banner-desk__action is-danger" title="Delete">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="banner-desk__no-match" x-show="visibleCount === 0" x-cloak>
                No banners match this search or filter.
            </p>

            @if ($banners->hasPages())
                <div class="banner-desk__pagination">
                    {{ $banners->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
