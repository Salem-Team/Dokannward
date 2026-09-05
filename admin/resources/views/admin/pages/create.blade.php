@extends('admin.layouts.app')

@section('title', 'Create Page')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="New text page"
            subtitle="Write with the visual editor — no code required."
            back-url="{{ route('admin.pages.index') }}"
        />

        <x-admin.card>
            <form action="{{ route('admin.pages.store') }}" method="POST" class="space-y-6" data-turbo="false">
                @csrf
                @include('admin.pages._form')
                <x-admin.form-actions class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.pages.index') }}">
                        <x-admin.button variant="secondary" type="button">Cancel</x-admin.button>
                    </a>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">Create page</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
