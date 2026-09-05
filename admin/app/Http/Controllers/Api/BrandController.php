<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public const LIST_CACHE_KEY = 'api.brands.list';

    /**
     * Full brand list for the storefront collections index.
     * Optional ?per_page= keeps pagination available for admin tooling.
     */
    public function index(Request $request)
    {
        $perPage = $request->filled('per_page')
            ? min(max((int) $request->integer('per_page'), 1), 200)
            : null;

        $generation = Cache::get('api.brands.list.generation') ?: 'v1';
        $cacheKey = self::LIST_CACHE_KEY.'.'.$generation.'.'.($perPage ?? 'all').'.'.app()->getLocale();

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage) {
            $query = Brand::query()->orderBy('name->en');

            if ($perPage !== null) {
                return BrandResource::collection($query->paginate($perPage))->response()->getData(true);
            }

            return BrandResource::collection($query->get())->response()->getData(true);
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.brands.list.generation', (string) Str::uuid());
        Cache::forget('admin.options.brands');
    }

    public function show(string $id)
    {
        $brand = Brand::query()
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id))
            ->firstOrFail();

        return new BrandResource($brand);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'description' => 'nullable|array',
            'country' => 'nullable|string',
            'logo_url' => 'nullable|url',
        ]);

        $nameEn = $data['name']['en'];

        $brand = Brand::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => UniqueSlug::make(Brand::class, $nameEn, 'slug', null, null, 'brand'),
            'description' => $data['description'] ?? null,
            'country' => $data['country'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
        ]);

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id)
    {
        $brand = Brand::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|array',
            'description' => 'sometimes|array',
            'country' => 'nullable|string',
            'logo_url' => 'nullable|url',
        ]);

        unset($data['slug']);
        $brand->update($data);

        return new BrandResource($brand->fresh());
    }

    public function destroy(string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return response()->json(null, 204);
    }
}
