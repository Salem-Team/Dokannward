{{--
    Category = what the item *is* (one per product, required).
    Collections = merchandising groups the item is *sold in* (many, optional,
    free to mix categories). Neither drives the other.
--}}
@php
    $selectedCollectionIds = array_map('strval', $selectedCollectionIds ?? []);
    $selectedCategoryId = $selectedCategoryId ?? null;
    $createCategoryUrl = route('admin.categories.create');
    $categoryOptions = $categories ?? [];
    $collectionChoices = $collections ?? [];
@endphp

<x-admin.card title="Categorization" icon="fas fa-sitemap" variant="default">
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5 -mt-1">
        <strong class="font-semibold text-gray-700 dark:text-gray-200">Category</strong> is what the item is —
        one per product, and it drives filters and breadcrumbs.
        <strong class="font-semibold text-gray-700 dark:text-gray-200">Collections</strong> are merchandising
        groups it is sold in (a collection can mix categories), and a product can sit in several at once.
    </p>

    {{-- Live summary of what this product will be filed under --}}
    <div id="categorization-path"
        class="mb-5 flex items-start gap-2 rounded-lg border border-dashed border-gray-200 dark:border-gray-600 bg-gray-50/80 dark:bg-gray-800/50 px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400 transition-colors"
        data-empty="Select a category"
        aria-live="polite">
        <i class="fas fa-folder-tree text-gray-400 shrink-0 mt-0.5"></i>
        <span id="categorization-path-text">Select a category</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="category_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                Category <span class="text-red-500">*</span>
            </label>
            <select id="category_id" name="category_id" required
                data-selected="{{ $selectedCategoryId }}"
                class="w-full rounded-lg border-2 transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-500/20 {{ $errors->first('category_id') ? 'border-red-500 dark:border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:border-blue-500 dark:focus:border-blue-500' }} px-4 py-3 text-gray-900 dark:text-white">
                <option value="">Select a category</option>
                @foreach ($categoryOptions as $id => $label)
                    <option value="{{ $id }}" @selected((string) $selectedCategoryId === (string) $id)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @if ($errors->first('category_id'))
                <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    {{ $errors->first('category_id') }}
                </p>
            @else
                <p id="category-help" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    What kind of product this is — bag, shoe, belt.
                    <a href="{{ $createCategoryUrl }}" class="underline font-medium text-primary">Add a category</a>
                </p>
            @endif
            @if (empty($categoryOptions))
                <p class="text-sm text-amber-600 dark:text-amber-400 mt-2">
                    No categories yet.
                    <a href="{{ $createCategoryUrl }}" class="underline font-medium">Create one first</a>.
                </p>
            @endif
        </div>

        <x-admin.select
            label="Brand"
            name="brand_id"
            :options="$brands ?? []"
            :selected="$selectedBrandId ?? null"
            placeholder="Select a brand"
            :required="true"
            :error="$errors->first('brand_id')"
            helpText="Which brand this product belongs to." />
    </div>

    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between gap-3 mb-2">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                Collections
            </label>
            <span id="collections-count" class="text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ count($selectedCollectionIds) }} selected
            </span>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            Optional. Pick every collection this product should appear in — collections are independent of
            the category, so “Summer 2026” can hold bags and shoes side by side.
        </p>

        <div id="collection-checkboxes" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @forelse ($collectionChoices as $choice)
                <label
                    class="collection-chip flex items-center gap-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700/40 px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 cursor-pointer transition-colors hover:border-primary/60">
                    <input type="checkbox" name="collection_ids[]" value="{{ $choice['id'] }}"
                        data-collection-name="{{ $choice['label'] }}"
                        @checked(in_array((string) $choice['id'], $selectedCollectionIds, true))
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="truncate">{{ $choice['label'] }}</span>
                    @unless ($choice['is_published'])
                        <span
                            class="ml-auto shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                            Hidden
                        </span>
                    @endunless
                </label>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400 sm:col-span-2 lg:col-span-3">
                    No collections yet — products stay reachable through their category.
                    <a href="{{ route('admin.collections.create') }}" class="underline font-medium">Create one</a>.
                </p>
            @endforelse
        </div>

        @error('collection_ids')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
            </p>
        @enderror
        @error('collection_ids.*')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                <i class="fas fa-exclamation-circle mr-1"></i> {{ $message }}
            </p>
        @enderror
    </div>
</x-admin.card>

@once
    @push('scripts')
        <script>
            // Category and Collections are independent: picking a category never
            // changes the collection checkboxes. This only keeps the summary line
            // and the chip styling in sync with what is checked.
            (function () {
                const categorySelect = document.getElementById('category_id');
                const collectionBox = document.getElementById('collection-checkboxes');
                const countEl = document.getElementById('collections-count');
                const pathEl = document.getElementById('categorization-path');
                const pathText = document.getElementById('categorization-path-text');

                if (!categorySelect || !pathEl || !pathText) return;

                const emptyPath = pathEl.dataset.empty || 'Select a category';
                const activeClasses = ['border-solid', 'border-blue-200', 'dark:border-blue-800', 'bg-blue-50/60', 'dark:bg-blue-950/30', 'text-blue-800', 'dark:text-blue-200'];
                const idleClasses = ['border-dashed', 'text-gray-500', 'dark:text-gray-400'];

                function checkedCollections() {
                    return [...(collectionBox?.querySelectorAll('input[type="checkbox"]:checked') ?? [])]
                        .map((input) => input.dataset.collectionName || '');
                }

                function refreshChipStyles() {
                    collectionBox?.querySelectorAll('.collection-chip').forEach((chip) => {
                        const checked = chip.querySelector('input').checked;
                        chip.classList.toggle('border-primary', checked);
                        chip.classList.toggle('bg-primary/5', checked);
                    });
                }

                function refresh() {
                    const categoryLabel = categorySelect.selectedOptions[0]?.text?.trim() || '';
                    const hasCategory = Boolean(categorySelect.value);
                    const collections = checkedCollections();

                    if (countEl) {
                        countEl.textContent = collections.length + ' selected';
                    }
                    refreshChipStyles();

                    if (!hasCategory) {
                        pathText.textContent = emptyPath;
                        pathEl.classList.add(...idleClasses);
                        pathEl.classList.remove(...activeClasses);
                        return;
                    }

                    pathText.textContent = collections.length
                        ? 'Category: ' + categoryLabel + ' · Collections: ' + collections.join(', ')
                        : 'Category: ' + categoryLabel + ' · no collections yet';
                    pathEl.classList.remove(...idleClasses);
                    pathEl.classList.add(...activeClasses);
                }

                categorySelect.addEventListener('change', refresh);
                collectionBox?.addEventListener('change', refresh);

                refresh();
            })();
        </script>
    @endpush
@endonce
