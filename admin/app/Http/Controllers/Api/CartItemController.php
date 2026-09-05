<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'cart_id' => 'required|uuid',
            'variant_id' => 'required|uuid',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric',
        ]);

        $data['id'] = (string) \Illuminate\Support\Str::uuid();
        $item = CartItem::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, string $id)
    {
        $item = CartItem::findOrFail($id);
        $data = $request->validate([
            'quantity' => 'sometimes|integer|min:1',
        ]);
        $item->update($data);
        return response()->json($item);
    }

    public function destroy(string $id)
    {
        $item = CartItem::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
