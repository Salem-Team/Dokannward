<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(private GeocodingService $geo) {}

    public function reverse(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->geo->reverse((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:180',
        ]);

        return response()->json([
            'data' => $this->geo->search($data['q']),
        ]);
    }

    public function ip(Request $request)
    {
        return response()->json([
            'data' => $this->geo->locateFromIp((string) $request->ip()),
        ]);
    }
}
