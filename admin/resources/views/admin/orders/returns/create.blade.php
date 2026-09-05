@extends('admin.layouts.app')

@section('title', 'Return · '.$order->order_number)

@php
    $reasons = [
        'changed_mind' => 'Changed mind',
        'damaged' => 'Damaged / defective',
        'wrong_item' => 'Wrong item',
        'size_fit' => 'Size / fit',
        'other' => 'Other',
    ];
@endphp

@push('styles')
    <style>
        .order-label {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #6b6b6b;
        }
        .order-mono {
            font-variant-numeric: tabular-nums;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        [x-cloak] { display: none !important; }
    </style>
@endpush

@section('content')
    @php
        $hasOld = session()->hasOldInput();
        $formItems = $order->items->map(function ($item) use ($returnedQtyByItem, $hasOld) {
            $returned = (int) ($returnedQtyByItem[$item->id] ?? 0);
            $available = max(0, (int) $item->qty - $returned);
            $oldItems = collect(old('items', []));
            $oldRow = $oldItems->firstWhere('order_item_id', $item->id);
            $selected = $hasOld ? ($oldRow !== null) : false;
            $qty = $oldRow
                ? min($available, max(1, (int) ($oldRow['qty'] ?? 1)))
                : ($available > 0 ? $available : 0);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => (float) $item->unit_price,
                'available' => $available,
                'selected' => $selected && $available > 0,
                'qty' => $qty,
            ];
        })->values();

        $returnableCount = $formItems->sum(fn ($row) => (int) $row['available']);
    @endphp
    <div class="space-y-6 w-full"
        x-data="returnForm(@js([
            'remaining' => (float) $remaining,
            'refundAmount' => (float) old('refund_amount', $remaining),
            'returnAll' => (bool) old('return_all', false),
            'restock' => old('restock', '1') !== '0',
            'items' => $formItems,
        ]))"
        x-cloak>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('admin.orders.show', $order->id) }}"
                class="inline-flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-zibra-ash hover:text-zibra-ink dark:hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-[10px]"></i>
                Back to order
            </a>
            <p class="order-mono text-sm text-zibra-ash">{{ $order->order_number }}</p>
        </div>

        <div class="border border-zibra-line dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
            <div class="px-6 sm:px-8 py-6 border-b border-zibra-line dark:border-gray-700">
                <p class="order-label mb-2">Issue return</p>
                <h1 class="text-2xl font-semibold tracking-tight text-zibra-ink dark:text-white">Credit note for this order</h1>
                <p class="mt-2 text-sm text-zibra-ash max-w-xl leading-relaxed">
                    Select what came back, then type the refund amount — full remaining balance or any partial amount you choose.
                </p>
            </div>

            @if ($remaining <= 0 || $returnableCount <= 0)
                <div class="px-6 sm:px-8 py-10 text-center">
                    <p class="text-sm text-zibra-ash">
                        @if ($remaining <= 0)
                            This order is fully refunded. Nothing left to return.
                        @else
                            Every line on this order has already been returned.
                        @endif
                    </p>
                    <a href="{{ route('admin.orders.show', $order->id) }}"
                        class="inline-flex mt-4 text-[11px] uppercase tracking-[0.16em] text-zibra-ink dark:text-white underline underline-offset-4">
                        Back to order
                    </a>
                </div>
            @else
                <form method="POST"
                    action="{{ route('admin.orders.returns.store', $order->id) }}"
                    class="px-6 sm:px-8 py-7 space-y-8"
                    @submit="if (!validateBeforeSubmit()) $event.preventDefault()">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="border border-zibra-line dark:border-gray-700 px-4 py-3">
                            <p class="order-label mb-1">Order total</p>
                            <p class="text-lg font-semibold tabular-nums text-zibra-ink dark:text-white">{{ \App\Support\Money::format($order->total_amount) }}</p>
                        </div>
                        <div class="border border-zibra-line dark:border-gray-700 px-4 py-3">
                            <p class="order-label mb-1">Already refunded</p>
                            <p class="text-lg font-semibold tabular-nums text-zibra-ink dark:text-white">{{ \App\Support\Money::format($refundedTotal) }}</p>
                        </div>
                        <div class="border border-zibra-ink dark:border-white px-4 py-3 bg-zibra-paper dark:bg-gray-900">
                            <p class="order-label mb-1">Still refundable</p>
                            <p class="text-lg font-semibold tabular-nums text-zibra-ink dark:text-white">{{ \App\Support\Money::format($remaining) }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <p class="order-label">Returned items</p>
                            <label class="inline-flex items-center gap-2 text-xs text-zibra-ash cursor-pointer">
                                <input type="checkbox" name="return_all" value="1" x-model="returnAll"
                                    class="border-zibra-line text-zibra-ink focus:ring-zibra-ink">
                                Return all remaining items
                            </label>
                        </div>

                        <div class="border border-zibra-line dark:border-gray-700 divide-y divide-zibra-line dark:divide-gray-700"
                            :class="returnAll && 'opacity-50 pointer-events-none'">
                            <template x-for="(item, index) in items" :key="item.id">
                                <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4"
                                    x-show="item.available > 0">
                                    <label class="flex items-start gap-3 flex-1 min-w-0 cursor-pointer">
                                        <input type="checkbox"
                                            :checked="item.selected"
                                            @change="toggleItem(item, $event.target.checked)"
                                            class="mt-1 border-zibra-line text-zibra-ink focus:ring-zibra-ink">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-zibra-ink dark:text-white" x-text="item.name"></span>
                                            <span class="block mt-1 text-xs text-zibra-ash">
                                                <span x-text="formatMoney(item.unit)"></span> ·
                                                <span x-text="item.available"></span> returnable
                                            </span>
                                        </span>
                                    </label>
                                    <div class="flex items-center gap-2 shrink-0" x-show="item.selected && !returnAll">
                                        <span class="order-label">Qty</span>
                                        <input type="number" min="1" :max="item.available"
                                            x-model.number="item.qty"
                                            @input="if (item.qty > item.available) item.qty = item.available; if (item.qty < 1) item.qty = 1; syncAmount()"
                                            class="w-20 h-9 border border-zibra-line dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-2 text-zibra-ink dark:text-white focus:outline-none focus:border-zibra-ink">
                                    </div>
                                    <template x-if="item.selected && !returnAll">
                                        <div>
                                            <input type="hidden" :name="'items['+index+'][order_item_id]'" :value="item.id">
                                            <input type="hidden" :name="'items['+index+'][qty]'" :value="item.qty">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        @error('items')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs text-zibra-ash" x-show="!hasSelection">
                            Choose at least one returnable line, or enable “Return all remaining items”.
                        </p>
                    </div>

                    <div>
                        <p class="order-label mb-3">Refund amount</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[12rem]">
                                <input type="number" name="refund_amount" step="0.01" min="0.01"
                                    :max="remaining"
                                    x-model.number="refundAmount"
                                    @blur="clampAmount()"
                                    required
                                    class="w-full h-12 border border-zibra-line dark:border-gray-600 bg-white dark:bg-gray-900 text-xl font-semibold tabular-nums px-4 text-zibra-ink dark:text-white focus:outline-none focus:border-zibra-ink @error('refund_amount') border-red-500 @enderror">
                            </div>
                            <button type="button" @click="useFull()"
                                class="h-12 px-4 text-[11px] uppercase tracking-[0.16em] border border-zibra-ink bg-zibra-ink text-white hover:bg-black transition-colors">
                                Full remaining
                            </button>
                            <button type="button" @click="useSuggested()"
                                class="h-12 px-4 text-[11px] uppercase tracking-[0.16em] border border-zibra-line dark:border-gray-600 text-zibra-ink dark:text-white hover:border-zibra-ink transition-colors">
                                Match items
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-zibra-ash">
                            Suggested from selected items:
                            <span class="tabular-nums text-zibra-ink dark:text-white" x-text="formatMoney(suggested)"></span>
                            · Max
                            <span class="tabular-nums" x-text="formatMoney(remaining)"></span>
                        </p>
                        @error('refund_amount')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="order-label block mb-2" for="reason">Reason</label>
                            <select name="reason" id="reason"
                                class="w-full h-10 border border-zibra-line dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 text-zibra-ink dark:text-white focus:outline-none focus:border-zibra-ink">
                                <option value="">Select…</option>
                                @foreach ($reasons as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reason')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="order-label block mb-2" for="notes">Notes</label>
                            <input type="text" name="notes" id="notes" value="{{ old('notes') }}" maxlength="2000"
                                placeholder="Optional note for the credit note"
                                class="w-full h-10 border border-zibra-line dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 text-zibra-ink dark:text-white focus:outline-none focus:border-zibra-ink">
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="restock" value="0">
                        <input type="checkbox" name="restock" value="1" x-model="restock"
                            class="mt-1 border-zibra-line text-zibra-ink focus:ring-zibra-ink">
                        <span>
                            <span class="block text-sm font-medium text-zibra-ink dark:text-white">Restock returned items</span>
                            <span class="block mt-0.5 text-xs text-zibra-ash">Add quantities back to variant inventory when a line is included.</span>
                        </span>
                    </label>

                    <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-zibra-line dark:border-gray-700">
                        <button type="submit"
                            :disabled="submitting || !hasSelection"
                            class="inline-flex items-center h-10 px-5 text-[11px] uppercase tracking-[0.16em] bg-zibra-ink text-white hover:bg-black transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            <span x-text="submitting ? 'Issuing…' : 'Issue return & credit note'"></span>
                        </button>
                        <a href="{{ route('admin.orders.show', $order->id) }}"
                            class="inline-flex items-center h-10 px-4 text-[11px] uppercase tracking-[0.16em] text-zibra-ash hover:text-zibra-ink dark:hover:text-white transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
