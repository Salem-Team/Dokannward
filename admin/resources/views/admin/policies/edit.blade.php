@extends('admin.layouts.app')

@section('title', $definition['title'])

@section('content')
    @php
        $liveUrl = $storefrontBase
            ? rtrim($storefrontBase, '/').'/policies/'.$slug
            : '/policies/'.$slug;
    @endphp

    <div class="brand-studio-page space-y-6">
        <div class="brand-studio-page__header">
            <div>
                <p class="brand-studio-page__eyebrow">
                    <a href="{{ route('admin.policies.index') }}" class="hover:underline underline-offset-2 opacity-70">
                        Policies
                    </a>
                    <span class="mx-1.5 opacity-40">/</span>
                    {{ $definition['title'] }}
                </p>
                <h1 class="brand-studio-page__title">Edit {{ $definition['title'] }}</h1>
                <p class="brand-studio-page__sub">
                    Live at
                    <a href="{{ $liveUrl }}" target="_blank" rel="noopener" class="font-medium underline underline-offset-2">
                        {{ $liveUrl }}
                    </a>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.policies.index') }}">
                    <x-admin.button variant="secondary" icon="fas fa-arrow-left">All policies</x-admin.button>
                </a>
                @if ($storefrontBase)
                    <a href="{{ $liveUrl }}" target="_blank" rel="noopener">
                        <x-admin.button variant="secondary" icon="fas fa-external-link-alt">Preview</x-admin.button>
                    </a>
                @endif
            </div>
        </div>

        <div class="website-hub__tip" role="note">
            <i class="fas fa-pen-fancy" aria-hidden="true"></i>
            <div>
                <p class="website-hub__tip-title">Writing tip</p>
                <p class="website-hub__tip-text">
                    {{ $definition['description'] }}
                    Press <strong>Enter</strong> for a new paragraph.
                    The website shows <strong>exactly</strong> this saved text — there is no separate hardcoded copy.
                </p>
            </div>
        </div>

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

        <form action="{{ route('admin.policies.update', $slug) }}" method="POST"
            class="brand-studio brand-studio--full space-y-6" data-turbo="false">
            @csrf
            @method('PUT')

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Page chrome</p>
                    <h2 class="brand-studio__section-title">Title &amp; visibility</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="policy_title">Page title</label>
                        <input id="policy_title" name="title" type="text" required maxlength="255"
                            value="{{ old('title', $page->title) }}"
                            class="brand-studio__input @error('title') brand-studio__input--error @enderror">
                        @error('title')
                            <p class="brand-studio__error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="brand-studio__label">URL (fixed)</label>
                        <div class="brand-studio__input flex items-center gap-2 opacity-80 cursor-default">
                            <code class="text-xs">/policies/{{ $slug }}</code>
                        </div>
                        <p class="brand-studio__hint mt-1.5">Canonical address — kept stable so footer &amp; SEO links never break.</p>
                    </div>
                </div>
                <div class="pt-1">
                    <x-admin.toggle name="published" label="Published — show on the website"
                        :checked="old('published', $page->published ?? true)" />
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Search engines</p>
                    <h2 class="brand-studio__section-title">SEO (optional)</h2>
                    <p class="brand-studio__hint mt-1">Leave blank to use the page title and a sensible default description.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="brand-studio__label" for="policy_meta_title">SEO title</label>
                        <input id="policy_meta_title" name="meta_title" type="text" maxlength="255"
                            value="{{ old('meta_title', $page->meta_title) }}"
                            class="brand-studio__input" placeholder="{{ $definition['title'] }}">
                    </div>
                    <div>
                        <label class="brand-studio__label" for="policy_meta_description">SEO description</label>
                        <input id="policy_meta_description" name="meta_description" type="text" maxlength="500"
                            value="{{ old('meta_description', $page->meta_description) }}"
                            class="brand-studio__input" placeholder="{{ $definition['meta_description'] }}">
                    </div>
                </div>
            </section>

            <section class="brand-studio__section space-y-5">
                <div>
                    <p class="brand-studio__eyebrow">Document</p>
                    <h2 class="brand-studio__section-title">Policy content</h2>
                    <p class="brand-studio__hint mt-1">This is the full text customers read on the page.</p>
                </div>

                @include('admin.partials.rich-editor', [
                    'name' => 'content',
                    'label' => 'Body',
                    'helpText' => 'Each Enter starts a new paragraph under the previous one. Save to publish to the website.',
                    'value' => old('content', $page->content ?? ''),
                ])
            </section>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-zibra-line">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Last saved
                    {{ optional($page->updated_at)->timezone(config('app.timezone'))->format('M j, Y · g:i A') ?? '—' }}
                </p>
                <div class="flex gap-3">
                    <a href="{{ route('admin.policies.index') }}">
                        <x-admin.button variant="secondary" type="button">Cancel</x-admin.button>
                    </a>
                    <x-admin.button variant="primary" type="submit" icon="fas fa-save">Save policy</x-admin.button>
                </div>
            </div>
        </form>

        <div class="rounded-xl border border-dashed border-zibra-line bg-zibra-paper/60 dark:bg-gray-900/40 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-zibra-ink dark:text-white">Need a clean starting point?</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Replace the current text with the professional starter template for this policy.
                </p>
            </div>
            <form action="{{ route('admin.policies.restore', $slug) }}" method="POST"
                onsubmit="return confirm('Replace the current text with the starter template? Your current wording will be overwritten.');">
                @csrf
                <x-admin.button variant="secondary" type="submit" icon="fas fa-rotate-left">
                    Restore starter
                </x-admin.button>
            </form>
        </div>
    </div>
@endsection
