<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SizeController extends Controller
{
    /**
     * Quick-create a custom size label from the product form (e.g. Medium, 42.5).
     * Returns an existing row when the name already exists (case-insensitive).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40'],
        ]);

        $name = trim(preg_replace('/\s+/u', ' ', $validated['name']) ?? '');
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter a size label.',
            ]);
        }

        $existing = Size::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return response()->json([
                'id' => $existing->id,
                'name' => $existing->name,
                'size_group' => $existing->size_group,
                'existing' => true,
            ]);
        }

        $maxCustomOrder = (int) Size::where('size_group', 'custom')->max('sort_order');
        $sortOrder = max(500, $maxCustomOrder + 10);

        $size = Size::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'size_group' => 'custom',
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $size->id,
            'name' => $size->name,
            'size_group' => $size->size_group,
            'existing' => false,
        ], 201);
    }
}
