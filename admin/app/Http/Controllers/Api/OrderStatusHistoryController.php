<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderStatusHistory;

class OrderStatusHistoryController extends Controller
{
    public function index(Request $request)
    {
        $q = OrderStatusHistory::query();
        if ($request->filled('order_id')) $q->where('order_id', $request->input('order_id'));
        return response()->json($q->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|uuid',
            'status' => 'required|string',
            'note' => 'nullable|string',
            'changed_by' => 'nullable|uuid',
        ]);

        $item = OrderStatusHistory::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid(), 'created_at' => now()], $data));
        return response()->json($item, 201);
    }
}
