<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies status transitions with milestone timestamps, audit history,
 * and inventory restock when an order is cancelled.
 */
class OrderLifecycle
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    /**
     * @param  array{status?: string, notes?: ?string, delivery_tracking_number?: ?string, note?: ?string, restock?: bool}  $changes
     */
    public function apply(Order $order, array $changes, ?string $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $changes, $actorId) {
            /** @var Order $locked */
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            $oldStatus = (string) $locked->status;
            $newStatus = array_key_exists('status', $changes)
                ? (string) $changes['status']
                : $oldStatus;

            if (! in_array($newStatus, self::STATUSES, true)) {
                $newStatus = $oldStatus;
            }

            $payload = [];
            if (array_key_exists('notes', $changes)) {
                $payload['notes'] = $changes['notes'];
            }
            if (array_key_exists('delivery_tracking_number', $changes)) {
                $payload['delivery_tracking_number'] = $changes['delivery_tracking_number'];
            }

            if ($newStatus !== $oldStatus) {
                $payload['status'] = $newStatus;
                $payload = array_merge($payload, $this->milestonePayload($locked, $newStatus, $actorId));

                if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                    $shouldRestock = (bool) ($changes['restock'] ?? true);
                    if ($shouldRestock) {
                        $this->restockItems($locked);
                    }
                }
            }

            if ($payload !== []) {
                $locked->fill($payload);
                $locked->save();
            }

            if ($newStatus !== $oldStatus) {
                $this->recordHistory(
                    $locked,
                    $newStatus,
                    $changes['note'] ?? $this->defaultNote($oldStatus, $newStatus),
                    $actorId
                );
                NotificationService::orderStatusChanged($locked->fresh(), $oldStatus, $newStatus);
            }

            return $locked->fresh(['items', 'statusHistories.changedBy', 'user', 'shippingAddress']);
        });
    }

    public function recordHistory(Order $order, string $status, ?string $note = null, ?string $actorId = null): OrderStatusHistory
    {
        return OrderStatusHistory::create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note,
            'changed_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    /**
     * Restock variant inventory for every line on the order.
     * Safe to call once on cancel; does not reverse later status changes.
     * Uses model updates (not query increment) so product `in_stock` stays in sync.
     */
    public function restockItems(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->variant_id || (int) $item->qty < 1) {
                continue;
            }

            $variant = ProductVariant::query()->lockForUpdate()->find($item->variant_id);
            if (! $variant) {
                continue;
            }

            $variant->update([
                'stock' => (int) $variant->stock + (int) $item->qty,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function milestonePayload(Order $order, string $newStatus, ?string $actorId): array
    {
        $now = now();
        $payload = [];

        match ($newStatus) {
            'processing' => $payload['confirmed_at'] = $order->confirmed_at ?? $now,
            'shipped' => $payload = [
                'confirmed_at' => $order->confirmed_at ?? $now,
                'shipped_at' => $order->shipped_at ?? $now,
            ],
            'delivered' => $payload = [
                'confirmed_at' => $order->confirmed_at ?? $now,
                'shipped_at' => $order->shipped_at ?? $now,
                'delivered_at' => $order->delivered_at ?? $now,
            ],
            'cancelled' => $payload = [
                'canceled_at' => $order->canceled_at ?? $now,
                'canceled_by' => $order->canceled_by ?? $actorId,
            ],
            'refunded' => $payload = [
                // Keep prior fulfillment milestones; refund is a terminal money state.
            ],
            default => null,
        };

        return $payload;
    }

    private function defaultNote(string $from, string $to): string
    {
        return "Status changed from {$from} to {$to}";
    }
}
