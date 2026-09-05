<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Photo;

class PhotoController extends Controller
{
    public function index(Request $request)
    {
        $q = Photo::query();
        if ($request->filled('imageable_type')) {
            $q->where('imageable_type', $request->input('imageable_type'));
        }
        if ($request->filled('imageable_id')) {
            $q->where('imageable_id', $request->input('imageable_id'));
        }
        return response()->json($q->paginate(40));
    }

    public function show(string $id)
    {
        return response()->json(Photo::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'imageable_type' => 'required|string',
            'imageable_id' => 'required|uuid',
            'storage_path' => 'required|string',
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'alt_text' => 'nullable|string',
            'is_primary' => 'boolean',
        ]);

        $photo = Photo::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($photo, 201);
    }

    public function update(Request $request, string $id)
    {
        $photo = Photo::findOrFail($id);
        $data = $request->validate([
            'alt_text' => 'nullable|string',
            'is_primary' => 'boolean',
            'position' => 'nullable|integer',
        ]);
        $photo->update($data);
        return response()->json($photo);
    }

    public function destroy(string $id)
    {
        $photo = Photo::findOrFail($id);
        $photo->delete();
        return response()->json(null, 204);
    }
}
