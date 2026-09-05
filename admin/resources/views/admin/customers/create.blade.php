@extends('admin.layouts.app')

@section('title', 'Create Customer')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            title="Create Customer"
            subtitle="Add a new customer account"
            back-url="{{ route('admin.customers.index') }}"
            back-label="Back to Customers"
        />

        <x-admin.card>
            <form action="{{ route('admin.customers.store') }}" method="POST" class="space-y-6">
                @csrf

                <x-admin.input name="name" label="Full Name" :value="old('name')" required :error="$errors->first('name')" />

                <x-admin.input name="email" type="email" label="Email Address" :value="old('email')" required
                    :error="$errors->first('email')" />

                <x-admin.input name="password" type="password" label="Password" required :error="$errors->first('password')"
                    helpText="Minimum 8 characters" />

                <x-admin.input name="password_confirmation" type="password" label="Confirm Password" required />

                <x-admin.form-actions>
                    <x-admin.button variant="secondary" type="button"
                        onclick="window.location='{{ route('admin.customers.index') }}'">
                        Cancel
                    </x-admin.button>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">
                        Create Customer
                    </x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
