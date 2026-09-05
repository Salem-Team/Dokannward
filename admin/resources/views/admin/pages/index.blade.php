@extends('admin.layouts.app')

@section('title', 'Pages')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header title="Text pages"
            subtitle='Custom long-form pages. Legal policies live under <a href="{{ route('admin.policies.index') }}" class="underline underline-offset-2">Policies</a>. For About / Contact / Menu, use <a href="{{ route('admin.website.index') }}" class="underline underline-offset-2">Website</a>.'>
            <x-slot name="actions">
                <a href="{{ route('admin.pages.create') }}">
                    <x-admin.button variant="primary" icon="fas fa-plus">Add page</x-admin.button>
                </a>
            </x-slot>
        </x-admin.page-header>

        <x-admin.card padding="p-0 sm:p-0">
            <x-admin.table hoverable>
                <x-slot name="header">
                    <x-admin.table-row>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </x-admin.table-row>
                </x-slot>

                @forelse ($pages as $page)
                    <x-admin.table-row>
                        <x-admin.table-cell>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $page->title }}</p>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <code class="text-xs text-gray-600 dark:text-gray-300">{{ $page->slug }}</code>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            @if ($page->published)
                                <x-admin.badge variant="success" size="sm">Published</x-admin.badge>
                            @else
                                <x-admin.badge variant="danger" size="sm">Draft</x-admin.badge>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $page->position }}</span>
                        </x-admin.table-cell>
                        <x-admin.table-cell class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.pages.edit', $page->id) }}"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.pages.destroy', $page->id) }}" method="POST" class="inline"
                                    data-confirm="Delete this page?" data-confirm-title="Delete page?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="5" class="text-center py-8">
                            <p class="text-gray-500 dark:text-gray-400">No pages yet</p>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table>

            @if ($pages->hasPages())
                <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $pages->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
