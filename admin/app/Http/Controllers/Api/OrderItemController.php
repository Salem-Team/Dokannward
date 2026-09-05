<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;

class OrderItemController extends Controller
{
    public function index(Request $request)
    {
        $q = OrderItem::query();
        if ($request->filled('order_id')) $q->where('order_id', $request->input('order_id'));
        return response()->json($q->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|uuid',
            'product_id' => 'required|uuid',
            'variant_id' => 'nullable|uuid',
            'sku' => 'required|string',
            'name' => 'required|string',
            'unit_price' => 'required|numeric',
            'qty' => 'required|integer|min:1',
            'line_total' => 'required|numeric',
        ]);

        $item = OrderItem::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($item, 201);
    }

    public function destroy(string $id)
    {
        OrderItem::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
