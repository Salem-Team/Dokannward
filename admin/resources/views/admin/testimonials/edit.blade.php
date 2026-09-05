@extends('admin.layouts.app')

@section('title', 'Edit Testimonial')

@section('content')
    <div class="admin-page-stack">
        <x-admin.page-header
            :title="'Edit Testimonial'"
            :subtitle="'Update “'.$testimonial->name.'”'"
            back-url="{{ route('admin.testimonials.index') }}"
            back-label="Back to Testimonials"
        />

        <x-admin.card>
            <form action="{{ route('admin.testimonials.update', $testimonial) }}" method="POST" class="space-y-6" data-turbo="false">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
                        <p class="font-semibold mb-1">Please fix the highlighted fields.</p>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.input name="name" label="Customer Name" type="text" required
                        placeholder="e.g. Dina Ibrahim"
                        :value="old('name', $testimonial->name)"
                        :error="$errors->first('name')" />

                    <x-admin.input name="title" label="Title/Position" type="text"
                        placeholder="CEO, Marketing Manager, etc."
                        :value="old('title', $testimonial->title)"
                        :error="$errors->first('title')" />

                    <x-admin.input name="company" label="Company" type="text"
                        placeholder="Company name (optional)"
                        :value="old('company', $testimonial->company)"
                        :error="$errors->first('company')" />

                    <x-admin.input name="avatar" label="Avatar URL" type="text"
                        placeholder="https://… (optional — leave blank for initials)"
                        helpText="Optional. Leave empty to show elegant initials on the site."
                        :value="old('avatar', $testimonial->avatar)"
                        :error="$errors->first('avatar')" />

                    <div class="md:col-span-2">
                        <x-admin.social-source :value="old('source', $testimonial->source)" />
                    </div>

                    <x-admin.star-rating
                        name="rating"
                        label="Rating"
                        :value="old('rating', $testimonial->rating)"
                        required
                        :error="$errors->first('rating')"
                        helpText="Click the stars — this is what customers see on the homepage."
                    />

                    <x-admin.input name="display_order" label="Display Order" type="number" min="0"
                        placeholder="0"
                        helpText="Lower numbers appear first (after Featured)."
                        :value="old('display_order', $testimonial->display_order)"
                        :error="$errors->first('display_order')" />
                </div>

                <x-admin.textarea name="content" label="Testimonial Content" required rows="5"
                    placeholder="Write the customer’s words…"
                    :value="old('content', $testimonial->content)"
                    :error="$errors->first('content')" />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.toggle
                        name="is_active"
                        label="Active — show on homepage"
                        helpText="Must stay on for the story to appear on dokannward.com."
                        :checked="(string) old('is_active', $testimonial->is_active ? '1' : '0') === '1'"
                    />
                    <x-admin.toggle
                        name="is_featured"
                        label="Featured — pin first"
                        helpText="Featured stories appear before the rest."
                        :checked="(string) old('is_featured', $testimonial->is_featured ? '1' : '0') === '1'"
                    />
                </div>

                <x-admin.form-actions class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.testimonials.index') }}">
                        <x-admin.button variant="secondary" type="button">Cancel</x-admin.button>
                    </a>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">
                        Update Testimonial
                    </x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </div>
@endsection
