@php
    use App\Models\Size;

    $selectedSizeIds = $selectedSizeIds ?? [];
    $sizesByGroup = $sizes->groupBy(fn ($size) => $size->size_group ?: Size::GROUP_SHOE);
@endphp

<p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
    Optional. Pick EU shoe sizes, clothing letters (S / M / L…), or add a custom label
    (e.g. Medium, Tall, 42.5). Leave empty for products without sizes — stock is tracked per color only.
</p>

<div id="size-chips" class="space-y-4" data-store-url="{{ route('admin.sizes.store') }}">
    @foreach ([Size::GROUP_SHOE, Size::GROUP_APPAREL] as $groupKey)
        @php $groupSizes = $sizesByGroup->get($groupKey, collect()); @endphp
        @continue($groupSizes->isEmpty())
        <div data-size-group="{{ $groupKey }}">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500 mb-2">
                {{ Size::GROUPS[$groupKey] }}
            </p>
            <div data-size-group-chips class="flex flex-wrap gap-2">
                @foreach ($groupSizes as $size)
                    <label class="size-chip inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300 cursor-pointer transition-colors">
                        <input type="checkbox" name="size_ids[]" value="{{ $size->id }}"
                            data-size-name="{{ $size->name }}"
                            @checked(in_array($size->id, $selectedSizeIds, true))
                            class="rounded border-gray-300 text-primary focus:ring-primary">
                        {{ $size->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    @php $customSizes = $sizesByGroup->get(Size::GROUP_CUSTOM, collect()); @endphp
    <div data-size-group="{{ Size::GROUP_CUSTOM }}" @class(['hidden' => $customSizes->isEmpty()])>
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500 mb-2">
            {{ Size::GROUPS[Size::GROUP_CUSTOM] }}
        </p>
        <div data-size-group-chips class="flex flex-wrap gap-2">
            @foreach ($customSizes as $size)
                <label class="size-chip inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300 cursor-pointer transition-colors">
                    <input type="checkbox" name="size_ids[]" value="{{ $size->id }}"
                        data-size-name="{{ $size->name }}"
                        @checked(in_array($size->id, $selectedSizeIds, true))
                        class="rounded border-gray-300 text-primary focus:ring-primary">
                    {{ $size->name }}
                </label>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-4 flex flex-wrap items-end gap-2">
    <div class="min-w-[160px] flex-1 max-w-xs">
        <label for="custom-size-name" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Add custom size</label>
        <input type="text" id="custom-size-name" maxlength="40" placeholder="e.g. Medium, Tall, 42.5"
            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2"
            autocomplete="off">
    </div>
    <button type="button" id="add-custom-size"
        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm font-medium px-3.5 py-2 hover:opacity-90 transition-opacity">
        <i class="fas fa-plus text-xs"></i>
        Add
    </button>
    <p id="custom-size-error" class="w-full text-xs text-red-500 hidden" role="alert"></p>
</div>

<p id="sizes-preview" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
    @if (count($selectedSizeIds))
        Sizes available {{ $sizes->whereIn('id', $selectedSizeIds)->sortBy('sort_order')->pluck('name')->implode(' / ') }}
    @else
        No sizes selected — stock is tracked per color only.
    @endif
</p>

<script>
(() => {
    const root = document.getElementById('size-chips');
    const addBtn = document.getElementById('add-custom-size');
    const nameInput = document.getElementById('custom-size-name');
    const errorEl = document.getElementById('custom-size-error');
    if (!root || !addBtn || !nameInput) return;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('"', '&quot;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');
    }

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message || '';
        errorEl.classList.toggle('hidden', !message);
    }

    function findChipByName(name) {
        const needle = String(name).toLowerCase();
        return [...root.querySelectorAll('input[type="checkbox"]')]
            .find((input) => (input.dataset.sizeName || '').toLowerCase() === needle);
    }

    function findChipById(id) {
        return root.querySelector(`input[type="checkbox"][value="${CSS.escape(id)}"]`);
    }

    function ensureCustomGroup() {
        const group = root.querySelector('[data-size-group="custom"]');
        group.classList.remove('hidden');
        return group.querySelector('[data-size-group-chips]');
    }

    function appendChip(id, name, checked = true) {
        const chips = ensureCustomGroup();
        const label = document.createElement('label');
        label.className = 'size-chip inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-600 dark:text-gray-300 cursor-pointer transition-colors';
        label.innerHTML = `
            <input type="checkbox" name="size_ids[]" value="${escapeHtml(id)}" data-size-name="${escapeHtml(name)}"
                class="rounded border-gray-300 text-primary focus:ring-primary" ${checked ? 'checked' : ''}>
            ${escapeHtml(name)}`;
        chips.appendChild(label);
        label.querySelector('input').dispatchEvent(new Event('change', { bubbles: true }));
        return label;
    }

    async function addCustomSize() {
        const name = nameInput.value.trim().replace(/\s+/g, ' ');
        showError('');
        if (!name) {
            showError('Enter a size label.');
            nameInput.focus();
            return;
        }

        const existing = findChipByName(name);
        if (existing) {
            existing.checked = true;
            existing.dispatchEvent(new Event('change', { bubbles: true }));
            nameInput.value = '';
            return;
        }

        addBtn.disabled = true;
        try {
            const res = await fetch(root.dataset.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data?.errors?.name?.[0] || data?.message || 'Could not add size.';
                showError(msg);
                return;
            }

            const already = findChipById(data.id) || findChipByName(data.name);
            if (already) {
                already.checked = true;
                already.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                appendChip(data.id, data.name, true);
            }
            nameInput.value = '';
        } catch (_) {
            showError('Could not add size. Try again.');
        } finally {
            addBtn.disabled = false;
            nameInput.focus();
        }
    }

    addBtn.addEventListener('click', addCustomSize);
    nameInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addCustomSize();
        }
    });
})();
</script>
