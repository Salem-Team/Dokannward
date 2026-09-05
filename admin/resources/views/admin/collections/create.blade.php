@extends('admin.layouts.app')

@section('title', 'Create Collection')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Catalog</p>
                <h1 class="brand-studio-page__title">Create collection</h1>
                <p class="brand-studio-page__sub">Title, cover image, and description for a top-level storefront group.</p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.collections.index') }}'">
                Back to Collections
            </x-admin.button>
        </div>
<div class="mb-8">
            @include('admin.collections._roadmap', ['variant' => 'setup'])
        </div>

        @include('admin.collections._form', ['action' => route('admin.collections.store')])
    </div>
@endsection

@push('scripts')
    @include('admin.collections._form-scripts')
@endpush
