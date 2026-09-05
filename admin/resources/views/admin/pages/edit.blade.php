@extends('admin.layouts.app')

@section('title', 'Edit Page')

@section('content')
    @php
        $pagePath = \App\Support\StorePolicies::isPolicySlug($page->slug)
            ? '/policies/'.$page->slug
            : '/pages/'.$page->slug;
    @endphp

    <div class="admin-page-stack">
        <x-admin.page-header
            :title="'Edit “'.$page->title.'”'"
            :subtitle="'Lives on the website at <code class=&quot;text-xs&quot;>'.$pagePath.'</code>'"
            back-url="{{ route('admin.pages.index') }}"
        />

        <x-admin.card>
            <form action="{{ route('admin.pages.update', $page->id) }}" method="POST" class="space-y-6" data-turbo="false">
                @csrf
                @method('PUT')
                @include('admin.pages._form', ['page' => $page])
                <x-admin.form-actions class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.pages.index') }}">
                        <x-admin.button variant="secondary" type="button">Cancel</x-admin.button>
                    </a>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save page</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
