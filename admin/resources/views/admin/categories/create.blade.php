@extends('admin.layouts.app')

@section('title', 'Create Category')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">Catalog</p>
                <h1 class="brand-studio-page__title">Create category</h1>
                <p class="brand-studio-page__sub">Add a classification with a homepage banner and optional logo under the hero.</p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.categories.index') }}'">
                Back to Categories
            </x-admin.button>
        </div>
        @include('admin.categories._form', [
            'action' => route('admin.categories.store'),
        ])
    </div>
@endsection

@push('scripts')
    @include('admin.categories._form-scripts')
@endpush
