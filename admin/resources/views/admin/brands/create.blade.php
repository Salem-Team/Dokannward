@extends('admin.layouts.app')

@section('title', 'Create Brand')

@section('content')
    <div class="brand-studio-page">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">The house</p>
                <h1 class="brand-studio-page__title">Create brand</h1>
                <p class="brand-studio-page__sub">Add a new house to the store collections index.</p>
            </div>
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.brands.index') }}'">
                Back to Brands
            </x-admin.button>
        </div>
@include('admin.brands._form', ['action' => route('admin.brands.store')])
    </div>
@endsection

@push('scripts')
    @include('admin.brands._form-scripts')
@endpush
