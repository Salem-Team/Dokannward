<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SliderItem;

class SliderItemController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'slider_id' => 'required|uuid',
            'photo_id' => 'required|uuid',
            'caption' => 'nullable|string',
            'link_url' => 'nullable|url',
            'position' => 'nullable|integer',
        ]);

        $item = SliderItem::create($data);
        return response()->json($item, 201);
    }

    public function destroy(string $slider_id, string $photo_id)
    {
        $item = SliderItem::where('slider_id',$slider_id)->where('photo_id',$photo_id)->firstOrFail();
        $item->delete();
        return response()->json(null, 204);
    }
}
