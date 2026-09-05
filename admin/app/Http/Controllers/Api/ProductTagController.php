<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductTag;

class ProductTagController extends Controller
{
    public function index()
    {
        return response()->json(ProductTag::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string']);
        $tag = ProductTag::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($tag, 201);
    }

    public function destroy(string $id)
    {
        ProductTag::findOrFail($id)->delete();
        return response()->json(null,204);
    }
}
