@extends('admin.layouts.app')

@section('title', 'Edit Category')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Catalog</p>
                <h1 class="brand-studio-page__title">Edit category</h1>
                <p class="brand-studio-page__sub">Update title, banner, logo, and description. Categories are classification only.</p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.categories.index') }}'">
                Back to Categories
            </x-admin.button>
        </div>
        @include('admin.categories._form', [
            'action' => route('admin.categories.update', $category->id),
            'category' => $category,
        ])
    </div>
@endsection

@push('scripts')
    @include('admin.categories._form-scripts')
@endpush
