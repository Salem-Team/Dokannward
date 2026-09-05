@extends('admin.layouts.app')

@section('title', 'Create Banner')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Website · Content</p>
                <h1 class="brand-studio-page__title">Create banner</h1>
                <p class="brand-studio-page__sub">
                    Full-bleed homepage CTA — syncs live to the storefront via the banners API.
                </p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.banners.index') }}'">
                Back to Banners
            </x-admin.button>
        </div>
@include('admin.banners._form', [
            'action' => route('admin.banners.store'),
            'storefrontBase' => $storefrontBase ?? config('app.frontend_url'),
        ])
    </div>
@endsection

@push('scripts')
    @include('admin.banners._form-scripts')
@endpush
