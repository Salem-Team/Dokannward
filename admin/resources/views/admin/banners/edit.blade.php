@extends('admin.layouts.app')

@section('title', 'Edit Banner')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Website · Content</p>
                <h1 class="brand-studio-page__title">Edit banner</h1>
                <p class="brand-studio-page__sub">
                    Changes publish to the homepage after save — cache clears automatically.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if (! empty($storefrontBase))
                    <x-admin.button variant="secondary" icon="fas fa-external-link-alt" size="sm"
                        onclick="window.open('{{ $storefrontBase }}/', '_blank')">
                        View homepage
                    </x-admin.button>
                @endif
                <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                    onclick="window.location='{{ route('admin.banners.index') }}'">
                    Back
                </x-admin.button>
            </div>
        </div>
@include('admin.banners._form', [
            'action' => route('admin.banners.update', $banner->id),
            'banner' => $banner,
            'storefrontBase' => $storefrontBase ?? config('app.frontend_url'),
        ])
    </div>
@endsection

@push('scripts')
    @include('admin.banners._form-scripts')
@endpush
