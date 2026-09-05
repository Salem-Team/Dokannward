<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryMovement;

class InventoryMovementController extends Controller
{
    public function index(Request $request)
    {
        $q = InventoryMovement::query();
        if ($request->filled('variant_id')) {
            $q->where('variant_id',$request->input('variant_id'));
        }
        return response()->json($q->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => 'required|uuid',
            'change' => 'required|integer',
            'source_type' => 'required|string',
            'source_id' => 'nullable|uuid',
            'reason' => 'nullable|string',
            'current_stock' => 'nullable|integer',
            'created_by' => 'nullable|uuid',
        ]);

        $movement = InventoryMovement::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid(), 'created_at' => now()], $data));
        return response()->json($movement, 201);
    }
}
