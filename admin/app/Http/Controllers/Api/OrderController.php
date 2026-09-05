<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json(Order::query()->with('items')->paginate(25));
    }

    public function show(string $id)
    {
        return response()->json(Order::with('items')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|uuid',
            'total_amount' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'shipping_amount' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'shipping_address_id' => 'required|uuid',
            'billing_address_id' => 'nullable|uuid',
            'notes' => 'nullable|string',
        ]);

        $order = Order::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($order, 201);
    }

    public function update(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $data = $request->validate([
            'status' => 'sometimes|string',
            'delivery_tracking_number' => 'nullable|string',
        ]);
        $order->update($data);
        return response()->json($order);
    }

    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        return response()->json(null, 204);
    }
}
