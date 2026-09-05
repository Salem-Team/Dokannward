@extends('admin.layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="Edit Customer"
            subtitle="Update customer information"
            back-url="{{ route('admin.customers.index') }}"
            back-label="Back to Customers"
        />

        <x-admin.card>
            <form action="{{ route('admin.customers.update', $customer->id) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <x-admin.input name="name" label="Full Name" :value="old('name', $customer->name)" required :error="$errors->first('name')" />

                <x-admin.input name="email" type="email" label="Email Address" :value="old('email', $customer->email)" required
                    :error="$errors->first('email')" />

                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Change Password</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leave blank to keep current password</p>

                    <x-admin.input name="password" type="password" label="New Password" :error="$errors->first('password')"
                        helpText="Minimum 8 characters" />

                    <x-admin.input name="password_confirmation" type="password" label="Confirm New Password" />
                </div>

                <x-admin.form-actions>
                    <x-admin.button variant="secondary" type="button"
                        onclick="window.location='{{ route('admin.customers.index') }}'">
                        Cancel
                    </x-admin.button>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">
                        Update Customer
                    </x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
