<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductVariant;

class ProductVariantController extends Controller
{
    public function index(Request $request)
    {
        $q = ProductVariant::query();
        if ($request->filled('product_id')) {
            $q->where('product_id', $request->input('product_id'));
        }
        if ($request->filled('color_id')) {
            $q->where('color_id', $request->input('color_id'));
        }
        if ($request->filled('size_id')) {
            $q->where('size_id', $request->input('size_id'));
        }
        return response()->json($q->paginate(30));
    }

    public function show(string $id)
    {
        return response()->json(ProductVariant::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|uuid',
            'sku' => 'required|string',
            'price' => 'required|numeric',
            'color_id' => 'nullable|uuid',
            'size_id' => 'nullable|uuid',
            'stock' => 'nullable|integer',
            'attributes' => 'nullable|array',
        ]);

        $variant = ProductVariant::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($variant, 201);
    }

    public function update(Request $request, string $id)
    {
        $variant = ProductVariant::findOrFail($id);
        $data = $request->validate([
            'sku' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'stock' => 'sometimes|integer',
            'attributes' => 'nullable|array',
        ]);

        $variant->update($data);
        return response()->json($variant);
    }

    public function destroy(string $id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();
        return response()->json(null, 204);
    }
}
