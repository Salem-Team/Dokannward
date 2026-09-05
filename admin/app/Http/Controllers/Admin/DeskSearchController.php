<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class DeskSearchController extends Controller
{
    /**
     * Quick desk search for the admin topbar (products, orders, customers).
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([
                'products' => [],
                'orders' => [],
                'customers' => [],
            ]);
        }

        $like = '%'.$q.'%';

        $products = Product::query()
            ->where(function ($query) use ($like) {
                $query->where('sku', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('name->en', 'like', $like)
                    ->orWhere('name->ar', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get(['id', 'sku', 'name', 'slug', 'status'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->translated_name,
                'meta' => $product->sku ?: $product->slug,
                'url' => route('admin.products.edit', $product),
            ])
            ->values();

        $orders = Order::query()
            ->with(['user:id,name,email', 'shippingAddress:id,recipient_name'])
            ->where(function ($query) use ($like, $q) {
                $query->where('order_number', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('id', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'order_number', 'user_id', 'customer_email', 'status', 'total_amount', 'shipping_address_id'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'label' => $order->order_number,
                'meta' => $order->customerName().' · '.strtoupper((string) $order->status),
                'url' => route('admin.orders.show', $order->id),
            ])
            ->values();

        $customers = User::query()
            ->where('is_admin', false)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name ?: $user->email,
                'meta' => $user->email ?: ($user->phone ?: 'Customer'),
                'url' => route('admin.customers.edit', $user),
            ])
            ->values();

        return response()->json([
            'products' => $products,
            'orders' => $orders,
            'customers' => $customers,
        ]);
    }
}
