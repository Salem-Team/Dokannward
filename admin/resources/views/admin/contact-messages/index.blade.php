@extends('admin.layouts.app')

@section('title', 'Contact Messages')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header title="Contact Messages"
            subtitle="Everything submitted through the storefront's Contact page lands here." />

        <div class="admin-filter-tabs">
            @foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label)
                <a href="{{ route('admin.contact-messages.index', array_filter(['status' => $key, 'search' => request('search')])) }}"
                    class="admin-filter-tabs__link {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }}
                    <span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <x-admin.input name="search" placeholder="Search name, email or message..." :value="request('search')" />
            <x-admin.button variant="primary" type="submit" icon="fas fa-search">Search</x-admin.button>
        </form>
        <x-admin.card>
            <x-admin.table hoverable>
                <x-slot name="header">
                    <x-admin.table-row>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            From
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Message
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Received
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </x-admin.table-row>
                </x-slot>

                @forelse ($messages as $message)
                    <x-admin.table-row class="{{ !$message->is_read ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                        <x-admin.table-cell>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $message->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $message->email }}</p>
                            @if ($message->phone)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $message->phone }}</p>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell class="max-w-md">
                            <a href="{{ route('admin.contact-messages.show', $message->id) }}"
                                class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 hover:text-blue-600 dark:hover:text-blue-400">
                                {{ $message->message }}
                            </a>
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            @if ($message->is_read)
                                <x-admin.badge variant="default" size="sm">Read</x-admin.badge>
                            @else
                                <x-admin.badge variant="info" size="sm">Unread</x-admin.badge>
                            @endif
                        </x-admin.table-cell>
                        <x-admin.table-cell>
                            <span class="text-sm text-gray-600 dark:text-gray-300" title="{{ $message->created_at }}">
                                {{ $message->created_at->diffForHumans() }}
                            </span>
                        </x-admin.table-cell>
                        <x-admin.table-cell class="text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.contact-messages.show', $message->id) }}" title="View"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="mailto:{{ $message->email }}?subject=Re: your message to {{ $brandDisplayName ?? 'our store' }}" title="Reply by email"
                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                    <i class="fas fa-reply"></i>
                                </a>
                                <form action="{{ route('admin.contact-messages.destroy', $message->id) }}" method="POST" class="inline"
                                    data-confirm="Delete this message permanently?" data-confirm-title="Delete message?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Delete"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @empty
                    <x-admin.table-row>
                        <x-admin.table-cell colspan="5" class="text-center py-8">
                            <i class="fas fa-envelope-open text-4xl text-gray-300 dark:text-gray-600 mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400">
                                @if ($status === 'unread')
                                    No unread messages — you're all caught up.
                                @else
                                    No contact messages found.
                                @endif
                            </p>
                        </x-admin.table-cell>
                    </x-admin.table-row>
                @endforelse
            </x-admin.table>

            @if ($messages->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $messages->links() }}
                </div>
            @endif
        </x-admin.card>
    </div>
@endsection
