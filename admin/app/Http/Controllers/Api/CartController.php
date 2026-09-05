<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;

class CartController extends Controller
{
    public function index()
    {
        return response()->json(Cart::query()->with('items')->paginate(25));
    }

    public function show(string $id)
    {
        return response()->json(Cart::with('items.variant')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|uuid',
            'session_token' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $cart = Cart::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($cart, 201);
    }

    public function destroy(string $id)
    {
        $cart = Cart::findOrFail($id);
        $cart->delete();
        return response()->json(null, 204);
    }
}
