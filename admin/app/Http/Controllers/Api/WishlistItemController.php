<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WishlistItem;

class WishlistItemController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'wishlist_id' => 'required|uuid',
            'variant_id' => 'required|uuid',
        ]);

        $wi = WishlistItem::create($data + ['added_at' => now()]);
        return response()->json($wi, 201);
    }

    public function destroy(string $wishlist_id, string $variant_id)
    {
        $wi = WishlistItem::where('wishlist_id', $wishlist_id)->where('variant_id', $variant_id)->firstOrFail();
        $wi->delete();
        return response()->json(null, 204);
    }
}
