@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'backUrl' => null,
    'backLabel' => 'Back',
])

<header {{ $attributes->merge(['class' => 'admin-page-header']) }}>
    @if ($backUrl)
        <x-admin.button variant="outline" icon="fas fa-arrow-left" size="sm"
            onclick="window.location='{{ $backUrl }}'">
            {{ $backLabel }}
        </x-admin.button>
    @endif

    <div class="admin-page-header__copy">
        @if ($eyebrow)
            <p class="admin-page-header__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="admin-page-header__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="admin-page-header__subtitle">{!! $subtitle !!}</p>
        @endif
    </div>

    @isset($actions)
        <div class="admin-page-header__actions">
            {{ $actions }}
        </div>
    @endisset
</header>
