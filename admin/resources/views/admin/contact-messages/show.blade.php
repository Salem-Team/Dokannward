@extends('admin.layouts.app')

@section('title', 'Contact Message')

@section('content')
    <div class="space-y-6 w-full">
        <div class="flex items-center justify-between">
            <x-admin.button variant="secondary" icon="fas fa-arrow-left" size="sm"
                onclick="window.location='{{ route('admin.contact-messages.index') }}'">
                Back to Messages
            </x-admin.button>

            <div class="flex items-center gap-2">
                <form action="{{ route('admin.contact-messages.toggleRead', $message->id) }}" method="POST" class="inline">
                    @csrf
                    <x-admin.button variant="secondary" size="sm"
                        icon="{{ $message->is_read ? 'fas fa-envelope' : 'fas fa-envelope-open' }}" type="submit">
                        {{ $message->is_read ? 'Mark as Unread' : 'Mark as Read' }}
                    </x-admin.button>
                </form>
                <form action="{{ route('admin.contact-messages.destroy', $message->id) }}" method="POST" class="inline"
                    data-confirm="Delete this message permanently?" data-confirm-title="Delete message?" data-confirm-confirm="Delete" data-confirm-tone="danger" data-confirm-eyebrow="Destructive action">
                    @csrf
                    @method('DELETE')
                    <x-admin.button variant="danger" icon="fas fa-trash" size="sm" type="submit">
                        Delete
                    </x-admin.button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <x-admin.alert type="success">
                {{ session('success') }}
            </x-admin.alert>
        @endif

        <x-admin.card>
            <x-slot name="header">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold">
                            {{ mb_strtoupper(mb_substr($message->name, 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $message->name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $message->email }}</p>
                        </div>
                    </div>
                    <x-admin.badge :variant="$message->is_read ? 'default' : 'info'">
                        {{ $message->is_read ? 'Read' : 'Unread' }}
                    </x-admin.badge>
                </div>
            </x-slot>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-400">Phone</dt>
                    <dd class="text-sm text-gray-900 dark:text-white mt-1">{{ $message->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-400">Received</dt>
                    <dd class="text-sm text-gray-900 dark:text-white mt-1">
                        {{ $message->created_at->format('M d, Y \a\t h:i A') }}
                        <span class="text-gray-400">({{ $message->created_at->diffForHumans() }})</span>
                    </dd>
                </div>
                @if ($message->ip_address)
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-400">IP Address</dt>
                        <dd class="text-sm text-gray-900 dark:text-white mt-1 font-mono">{{ $message->ip_address }}</dd>
                    </div>
                @endif
            </dl>

            <div>
                <dt class="text-xs uppercase tracking-wider text-gray-400 mb-2">Message</dt>
                <dd class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    {{ $message->message }}
                </dd>
            </div>

            <div class="mt-6 flex justify-end">
                <x-admin.button variant="primary" icon="fas fa-reply"
                    onclick="window.location='mailto:{{ $message->email }}?subject=Re: your message to {{ $brandDisplayName ?? 'our store' }}'">
                    Reply by Email
                </x-admin.button>
            </div>
        </x-admin.card>
    </div>
@endsection
