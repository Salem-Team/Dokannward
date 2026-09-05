<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\ProductVariant;
use App\Services\NotificationService;
use App\Services\OrderLifecycle;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderReturnController extends Controller
{
    private const REASONS = [
        'changed_mind',
        'damaged',
        'wrong_item',
        'size_fit',
        'other',
    ];

    public function __construct(
        private OrderLifecycle $lifecycle,
    ) {}

    public function create(string $orderId)
    {
        $order = Order::with(['items.variant.color', 'user', 'shippingAddress', 'returns'])
            ->findOrFail($orderId);

        $refundedTotal = (float) $order->returns()->sum('refund_amount');
        $remaining = max(0, round((float) $order->total_amount - $refundedTotal, 2));

        $returnedQtyByItem = OrderReturnItem::query()
            ->whereHas('orderReturn', fn ($q) => $q->where('order_id', $order->id))
            ->selectRaw('order_item_id, SUM(qty) as returned_qty')
            ->groupBy('order_item_id')
            ->pluck('returned_qty', 'order_item_id');

        return view('admin.orders.returns.create', compact('order', 'remaining', 'refundedTotal', 'returnedQtyByItem'));
    }

    public function store(Request $request, string $orderId)
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => ['nullable', 'string', 'max:100', Rule::in(self::REASONS)],
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.order_item_id' => 'required_with:items|uuid|exists:order_items,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'return_all' => 'nullable|boolean',
            'restock' => 'nullable|boolean',
        ]);

        $restock = $request->boolean('restock', true);
        $returnAll = $request->boolean('return_all');

        $return = DB::transaction(function () use ($orderId, $validated, $returnAll, $restock) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($orderId);

            $refundedTotal = (float) $order->returns()->sum('refund_amount');
            $remaining = max(0, round((float) $order->total_amount - $refundedTotal, 2));
            $refundAmount = round((float) $validated['refund_amount'], 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'This order is fully refunded. Nothing left to return.',
                ]);
            }

            if ($refundAmount > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Refund cannot exceed the remaining refundable amount ('.number_format($remaining, 2).').',
                ]);
            }

            $returnedQtyByItem = OrderReturnItem::query()
                ->whereHas('orderReturn', fn ($q) => $q->where('order_id', $order->id))
                ->selectRaw('order_item_id, SUM(qty) as returned_qty')
                ->groupBy('order_item_id')
                ->pluck('returned_qty', 'order_item_id');

            $selectedItems = $this->resolveSelectedItems($order, $validated, $returnAll, $returnedQtyByItem);

            if ($selectedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one returned item, or enable “Return all remaining items”.',
                ]);
            }

            $suggested = 0.0;
            foreach ($selectedItems as $row) {
                $suggested += (float) $row['order_item']->unit_price * $row['qty'];
            }
            $suggested = round($suggested > 0 ? min($suggested, $remaining) : 0, 2);

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'status' => 'refunded',
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'refund_amount' => $refundAmount,
                'suggested_amount' => $suggested,
                'restock' => $restock,
                'processed_by' => auth()->id(),
                'refunded_at' => now(),
            ]);

            foreach ($selectedItems as $row) {
                /** @var OrderItem $item */
                $item = $row['order_item'];
                $qty = $row['qty'];

                OrderReturnItem::create([
                    'order_return_id' => $return->id,
                    'order_item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price' => $item->unit_price,
                    'line_refund' => round((float) $item->unit_price * $qty, 2),
                ]);

                if ($restock && $item->variant_id) {
                    $variant = ProductVariant::query()->lockForUpdate()->find($item->variant_id);
                    if ($variant) {
                        $variant->update([
                            'stock' => (int) $variant->stock + $qty,
                        ]);
                    }
                }
            }

            // Any recorded refund flips the order into the refunded status automatically.
            if ($order->status !== 'refunded') {
                $this->lifecycle->apply($order, [
                    'status' => 'refunded',
                    'note' => 'Status set to refunded after credit note '.$return->return_number,
                    'restock' => false,
                ], auth()->id());
            }

            return $return->load('items');
        });

        $order = Order::findOrFail($orderId);
        NotificationService::orderReturnCreated($order, $return);

        return redirect()
            ->route('admin.orders.show', $order->id)
            ->with('success', "Return {$return->return_number} recorded for ".Money::format($return->refund_amount).'. Order marked as refunded.')
            ->with('return_credit_note_id', $return->id);
    }

    public function creditNote(string $orderId, string $returnId)
    {
        $return = OrderReturn::with([
            'items.orderItem.variant.color',
            'order.user',
            'order.items',
            'order.shippingAddress',
            'processedBy',
        ])
            ->where('order_id', $orderId)
            ->findOrFail($returnId);

        $order = $return->order;

        $pdf = Pdf::loadView('admin.orders.returns.credit-note', compact('order', 'return'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download('credit-note-'.$return->return_number.'.pdf');
    }

    public function destroy(string $orderId, string $returnId)
    {
        $return = OrderReturn::with('items.orderItem')
            ->where('order_id', $orderId)
            ->findOrFail($returnId);

        $number = $return->return_number;

        DB::transaction(function () use ($return) {
            if ($return->restock) {
                foreach ($return->items as $returnItem) {
                    $variantId = $returnItem->orderItem?->variant_id;
                    if (! $variantId) {
                        continue;
                    }

                    $variant = ProductVariant::query()->lockForUpdate()->find($variantId);
                    if (! $variant) {
                        continue;
                    }

                    // Never drive inventory negative when reversing a mistaken credit note.
                    $next = max(0, (int) $variant->stock - (int) $returnItem->qty);
                    $variant->update(['stock' => $next]);
                }
            }

            $return->delete();
        });

        return redirect()
            ->route('admin.orders.show', $orderId)
            ->with('success', "Return {$number} removed.");
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int|string>  $returnedQtyByItem
     * @return list<array{order_item: OrderItem, qty: int}>
     */
    private function resolveSelectedItems(Order $order, array $validated, bool $returnAll, $returnedQtyByItem): array
    {
        $selectedItems = [];

        if ($returnAll) {
            foreach ($order->items as $item) {
                $already = (int) ($returnedQtyByItem[$item->id] ?? 0);
                $available = max(0, (int) $item->qty - $already);
                if ($available > 0) {
                    $selectedItems[] = [
                        'order_item' => $item,
                        'qty' => $available,
                    ];
                }
            }

            return $selectedItems;
        }

        if (empty($validated['items'])) {
            return [];
        }

        $itemsById = $order->items->keyBy('id');

        foreach ($validated['items'] as $row) {
            $item = $itemsById->get($row['order_item_id']);
            if (! $item) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected items does not belong to this order.',
                ]);
            }

            $already = (int) ($returnedQtyByItem[$item->id] ?? 0);
            $available = max(0, (int) $item->qty - $already);

            if ((int) $row['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Qty for \"{$item->name}\" exceeds what is still returnable ({$available} left).",
                ]);
            }

            $selectedItems[] = [
                'order_item' => $item,
                'qty' => (int) $row['qty'],
            ];
        }

        return $selectedItems;
    }
}
