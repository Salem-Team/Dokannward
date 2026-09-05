<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slider;

class SliderController extends Controller
{
    public function index()
    {
        return response()->json(Slider::query()->simplePaginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $model = Slider::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));
        return response()->json($model, 201);
    }

    public function destroy(string $id)
    {
        Slider::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
