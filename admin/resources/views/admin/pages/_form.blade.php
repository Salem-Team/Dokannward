@php
    $page = $page ?? null;
    $knownSlugs = [
        'about' => 'About page body (optional override)',
        'privacy-policy' => 'Privacy Policy',
        'terms-of-service' => 'Terms of Service',
        'refund-policy' => 'Refund Policy',
        'shipping-policy' => 'Shipping Policy',
    ];
@endphp

<div class="website-hub__tip mb-6">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
    <div>
        <p class="website-hub__tip-title">Writing tip</p>
        <p class="website-hub__tip-text">
            Use the toolbar below — bold, headings, lists, and links. No HTML knowledge needed.
            Turn <strong>Published</strong> on when the page should appear on the live site.
        </p>
    </div>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
        <p class="font-semibold mb-1">Please fix the highlighted fields.</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <x-admin.input name="title" label="Page title" required
        helpText="Shown as the main heading on the website"
        :value="old('title', $page->title ?? '')"
        :error="$errors->first('title')" />

    <div>
        <x-admin.input name="slug" label="Page address"
            helpText="Leave blank when creating — we generate it from the title. Examples: privacy-policy, about"
            :value="old('slug', $page->slug ?? '')"
            :error="$errors->first('slug')" />
        @if (! $page)
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Common pages:
                @foreach ($knownSlugs as $slug => $label)
                    <code class="mx-0.5">{{ $slug }}</code>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </div>

    <x-admin.input name="meta_title" label="SEO title (optional)"
        helpText="Shown in Google search results — leave blank to use the page title"
        :value="old('meta_title', $page->meta_title ?? '')"
        :error="$errors->first('meta_title')" />

    <x-admin.input name="position" label="Sort order" type="number" min="0"
        helpText="Lower numbers appear first in the pages list"
        :value="old('position', $page->position ?? 0)"
        :error="$errors->first('position')" />
</div>

<x-admin.textarea name="meta_description" label="SEO description (optional)" rows="2"
    helpText="Short summary for search engines (about 150 characters)"
    :value="old('meta_description', $page->meta_description ?? '')"
    :error="$errors->first('meta_description')" />

@include('admin.partials.rich-editor', [
    'name' => 'content',
    'label' => 'Page content',
    'helpText' => 'This is what customers read on the page.',
    'value' => old('content', $page->content ?? ''),
])

<x-admin.toggle name="published" label="Published — show on the website"
    :checked="old('published', $page->published ?? true)" />
