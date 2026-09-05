<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderLifecycle;
use Illuminate\Console\Command;

/**
 * One-shot / deploy-safe sync: any order with a credit note becomes refunded.
 */
class SyncRefundedOrderStatus extends Command
{
    protected $signature = 'orders:sync-refunded-status';

    protected $description = 'Set status=refunded on orders that already have returns but still show another status';

    public function handle(OrderLifecycle $lifecycle): int
    {
        $orders = Order::query()
            ->whereHas('returns')
            ->where('status', '!=', 'refunded')
            ->orderBy('created_at')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No orders need a refunded status sync.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($orders as $order) {
            $lifecycle->apply($order, [
                'status' => 'refunded',
                'note' => 'Backfill: status set to refunded because a credit note already exists',
                'restock' => false,
            ], null);
            $count++;
            $this->line("{$order->order_number} → refunded");
        }

        $this->info("Synced {$count} order(s) to refunded.");

        return self::SUCCESS;
    }
}
