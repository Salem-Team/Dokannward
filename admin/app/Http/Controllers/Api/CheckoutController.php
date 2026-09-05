<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Stateless checkout endpoint for the decoupled Next.js storefront.
 *
 * The Next.js cart lives entirely client-side, so instead of relying on the
 * server-side Cart/CartItem session flow used by the bundled Blade storefront,
 * this accepts the full cart payload in one request and creates the
 * Address + Order + OrderItems atomically — re-pricing and decrementing stock
 * from the database so the client cannot underpay or oversell.
 */
class CheckoutController extends Controller
{
    public function __construct(private OrderPlacementService $orders) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'line_1' => 'required|string',
            'line_2' => 'nullable|string',
            'city' => 'required|string',
            'postal_code' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'place_name' => 'nullable|string|max:255',
            'location_source' => 'nullable|in:map,gps,search,typed',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string|max:40',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.variant_id' => 'nullable|string',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.sku' => 'nullable|string',
        ]);

        try {
            $order = $this->orders->place($data);
        } catch (ValidationException $e) {
            throw $e;
        }

        $payment = collect(OrderPlacementService::paymentOptions()['methods'])
            ->firstWhere('key', $order->payment_method);

        return response()->json([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'subtotal' => $order->subtotal,
            'shipping_amount' => $order->shipping_amount,
            'tax_amount' => $order->tax_amount,
            'payment_method' => $order->payment_method,
            'payment_label' => $payment['label'] ?? OrderPlacementService::paymentLabel($order->payment_method),
            'payment_instructions' => $payment['instructions'] ?? null,
        ], 201);
    }

    public function show(string $id)
    {
        $order = Order::with('items')
            ->where('id', $id)
            ->orWhere('order_number', $id)
            ->firstOrFail();

        return response()->json($order);
    }
}
