<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Audit;

class AuditController extends Controller
{
    public function index()
    {
        return response()->json(Audit::query()->latest()->paginate(50));
    }

    public function show(string $id)
    {
        return response()->json(Audit::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|uuid',
            'action' => 'required|string',
            'object_type' => 'nullable|string',
            'object_id' => 'nullable|uuid',
            'payload' => 'nullable|array',
        ]);

        $record = Audit::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($record, 201);
    }
}
