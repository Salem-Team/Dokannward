<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomepageSectionController extends Controller
{
    public function index()
    {
        return response()->json(HomepageSection::query()->simplePaginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alias' => 'nullable|string|max:255',
            'type' => 'required|string',
            'config' => 'nullable|array',
            'position' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $aliasSource = $data['alias'] ?? $data['type'];

        $item = HomepageSection::create([
            'id' => (string) Str::uuid(),
            'alias' => UniqueSlug::make(HomepageSection::class, $aliasSource, 'alias', null, null, 'section'),
            'type' => $data['type'],
            'config' => $data['config'] ?? null,
            'position' => $data['position'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($item, 201);
    }

    public function destroy(string $id)
    {
        HomepageSection::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
