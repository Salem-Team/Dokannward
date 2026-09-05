@extends('admin.layouts.app')

@section('title', 'Profile')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="Profile Settings"
            subtitle="Manage your account information and password"
        />

        @if (session('success'))
            <div class="admin-flash admin-flash--success rounded-xl border px-4 py-3 text-sm" role="status">
                {{ session('success') }}
            </div>
        @endif

        <x-admin.card>
            <x-slot name="header">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2>
            </x-slot>

            <form action="{{ route('admin.profile.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="name" label="Full Name" value="{{ old('name', auth()->user()->name) }}"
                        required :error="$errors->first('name')" autocomplete="name" />
                    <x-admin.input name="email" type="email" label="Email Address"
                        value="{{ old('email', auth()->user()->email) }}" required
                        :error="$errors->first('email')" autocomplete="username" />
                </div>

                <x-admin.form-actions>
                    <x-admin.button type="submit" icon="fas fa-save">
                        Update Profile
                    </x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card>
            <x-slot name="header">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h2>
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Use at least 12 characters with upper/lowercase, a number, and a symbol.
            </p>

            <form action="{{ route('admin.profile.password') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <x-admin.input name="current_password" type="password" label="Current Password" required
                    :error="$errors->first('current_password')" autocomplete="current-password" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="password" type="password" label="New Password" required
                        :error="$errors->first('password')" autocomplete="new-password" />
                    <x-admin.input name="password_confirmation" type="password" label="Confirm New Password" required
                        autocomplete="new-password" />
                </div>

                <x-admin.form-actions>
                    <x-admin.button type="submit" icon="fas fa-key">
                        Update Password
                    </x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
