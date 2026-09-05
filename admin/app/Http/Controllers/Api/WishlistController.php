<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    public function index()
    {
        return response()->json(Wishlist::query()->with('items')->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid',
            'name' => 'nullable|string',
        ]);

        $wishlist = Wishlist::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($wishlist, 201);
    }

    public function destroy(string $id)
    {
        $w = Wishlist::findOrFail($id);
        $w->delete();
        return response()->json(null, 204);
    }
}
