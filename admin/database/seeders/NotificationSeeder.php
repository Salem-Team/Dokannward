<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notifications = [
            [
                'type' => 'order',
                'title' => 'New order received',
                'message' => 'Order #ORD-2024-001 from John Doe',
                'icon' => 'fa-shopping-cart',
                'color' => 'blue',
                'link' => '/admin/orders',
                'is_read' => false,
                'created_at' => now()->subMinutes(5),
            ],
            [
                'type' => 'low_stock',
                'title' => 'Low stock alert',
                'message' => 'Product "Premium Leather Backpack" has 3 units left',
                'icon' => 'fa-exclamation-triangle',
                'color' => 'yellow',
                'link' => '/admin/inventory',
                'is_read' => false,
                'created_at' => now()->subHour(),
            ],
            [
                'type' => 'payment',
                'title' => 'Payment received',
                'message' => '$250.00 from order #ORD-2024-002',
                'icon' => 'fa-check',
                'color' => 'green',
                'link' => '/admin/orders',
                'is_read' => false,
                'created_at' => now()->subHours(2),
            ],
            [
                'type' => 'customer',
                'title' => 'New customer registered',
                'message' => 'Sarah Smith (sarah@example.com) just signed up',
                'icon' => 'fa-user-plus',
                'color' => 'blue',
                'link' => '/admin/customers',
                'is_read' => true,
                'read_at' => now()->subMinutes(30),
                'created_at' => now()->subHours(3),
            ],
            [
                'type' => 'order',
                'title' => 'Order status updated',
                'message' => 'Order #ORD-2024-003 has been shipped',
                'icon' => 'fa-truck',
                'color' => 'blue',
                'link' => '/admin/orders',
                'is_read' => true,
                'read_at' => now()->subHours(2),
                'created_at' => now()->subHours(4),
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}
