<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Prefer relative admin paths so links stay valid across localhost / 127.0.0.1 / prod hosts.
     */
    private static function adminLink(string $name, mixed $params = []): string
    {
        return route($name, $params, absolute: false);
    }

    /**
     * Create a new order notification
     */
    public static function newOrder($order)
    {
        return Notification::create([
            'type' => 'order',
            'title' => 'New order received',
            'message' => "Order #{$order->order_number} from " . (
                method_exists($order, 'customerName')
                    ? $order->customerName()
                    : ($order->user->name ?? $order->shippingAddress?->recipient_name ?? 'Guest')
            ),
            'icon' => 'fa-shopping-cart',
            'color' => 'blue',
            'link' => self::adminLink('admin.orders.show', $order->id),
            'related_id' => $order->id,
        ]);
    }

    /**
     * Create a low stock notification
     */
    public static function lowStock($product, $stockLevel)
    {
        $productName = is_array($product->name)
            ? ($product->name[app()->getLocale()] ?? $product->name['en'] ?? 'Product')
            : $product->name;

        return Notification::create([
            'type' => 'low_stock',
            'title' => 'Low stock alert',
            'message' => "\"{$productName}\" has {$stockLevel} units left",
            'icon' => 'fa-exclamation-triangle',
            'color' => 'yellow',
            'link' => self::adminLink('admin.inventory.index'),
            'related_id' => $product->id,
        ]);
    }

    /**
     * Create a payment received notification
     */
    public static function paymentReceived($order, $amount)
    {
        return Notification::create([
            'type' => 'payment',
            'title' => 'Payment received',
            'message' => '$' . number_format($amount, 2) . " from order #{$order->order_number}",
            'icon' => 'fa-check',
            'color' => 'green',
            'link' => self::adminLink('admin.orders.show', $order->id),
            'related_id' => $order->id,
        ]);
    }

    /**
     * Create a new customer notification
     */
    public static function newCustomer($customer)
    {
        return Notification::create([
            'type' => 'customer',
            'title' => 'New customer registered',
            'message' => "{$customer->name} ({$customer->email}) just signed up",
            'icon' => 'fa-user-plus',
            'color' => 'blue',
            'link' => self::adminLink('admin.customers.edit', $customer->id),
            'related_id' => $customer->id,
        ]);
    }

    /**
     * Create a new contact message notification
     */
    public static function newContactMessage($contactMessage)
    {
        return Notification::create([
            'type' => 'contact_message',
            'title' => 'New contact message',
            'message' => "{$contactMessage->name} sent a message via the Contact page",
            'icon' => 'fa-envelope',
            'color' => 'blue',
            'link' => self::adminLink('admin.contact-messages.show', $contactMessage->id),
            'related_id' => $contactMessage->id,
        ]);
    }

    /**
     * Create a pending product review notification
     */
    public static function newReview($review)
    {
        $productName = $review->product
            ? (is_array($review->product->name)
                ? ($review->product->name[app()->getLocale()] ?? $review->product->name['en'] ?? 'Product')
                : $review->product->name)
            : 'a product';

        return Notification::create([
            'type' => 'review',
            'title' => 'New review pending',
            'message' => "{$review->reviewer_name} left {$review->rating}★ on {$productName}",
            'icon' => 'fa-star',
            'color' => 'yellow',
            'link' => self::adminLink('admin.reviews.index'),
            'related_id' => $review->id,
        ]);
    }

    /**
     * Create an order status changed notification
     */
    public static function orderStatusChanged($order, $oldStatus, $newStatus)
    {
        $statusColors = [
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'blue',
            'delivered' => 'green',
            'cancelled' => 'red',
        ];

        return Notification::create([
            'type' => 'order',
            'title' => 'Order status updated',
            'message' => "Order #{$order->order_number} changed from {$oldStatus} to {$newStatus}",
            'icon' => 'fa-sync',
            'color' => $statusColors[$newStatus] ?? 'blue',
            'link' => self::adminLink('admin.orders.show', $order->id),
            'related_id' => $order->id,
        ]);
    }

    /**
     * Admin issued a return / credit note against an order.
     */
    public static function orderReturnCreated($order, $return)
    {
        $amount = method_exists(\App\Support\Money::class, 'format')
            ? \App\Support\Money::format($return->refund_amount)
            : number_format((float) $return->refund_amount, 2);

        return Notification::create([
            'type' => 'order',
            'title' => 'Return recorded',
            'message' => "Credit note {$return->return_number} for order #{$order->order_number} — {$amount}",
            'icon' => 'fa-undo',
            'color' => 'yellow',
            'link' => self::adminLink('admin.orders.show', $order->id),
            'related_id' => $order->id,
        ]);
    }
}
