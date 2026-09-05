<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Collection;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public const LIST_CACHE_KEY = 'api.categories.tree';

    /**
     * Nested catalog tree for the storefront.
     *
     * Each published Collection is a synthetic root whose `children` are the
     * distinct categories of its direct active products (`collection_product`).
     * Every Category is also returned as an independent root so classification
     * stays reachable without belonging to a collection.
     */
    public function index()
    {
        $generation = Cache::get('api.categories.list.generation') ?: 'v1';
        $payload = Cache::remember(self::LIST_CACHE_KEY.'.'.$generation.'.'.app()->getLocale(), now()->addMinutes(10), function () {
            $data = [];

            $collections = Collection::query()
                ->published()
                ->orderBy('position')
                ->orderBy('name->en')
                ->get();

            foreach ($collections as $collection) {
                $slug = is_array($collection->slug) ? ($collection->slug['en'] ?? null) : $collection->slug;
                $slugAr = is_array($collection->slug) ? ($collection->slug['ar'] ?? null) : null;
                $memberCategories = $collection->categoriesFromMembers();

                $data[] = [
                    'id' => $collection->id,
                    'name' => $collection->translated_name,
                    'slug' => $slug,
                    'slug_ar' => $slugAr,
                    'description' => $collection->description,
                    'image' => $collection->image_url,
                    'logo_url' => null,
                    'parent_id' => null,
                    'collection_id' => $collection->id,
                    'is_published' => true,
                    'position' => (int) $collection->position,
                    'children' => CategoryResource::collection($memberCategories)->resolve(),
                ];
            }

            // All categories as independent classification roots.
            $categories = Category::query()
                ->orderBy('position')
                ->orderBy('name->en')
                ->get();

            foreach ($categories as $category) {
                $node = (new CategoryResource($category))->resolve();
                $node['children'] = [];
                $node['is_published'] = true;
                $data[] = $node;
            }

            return ['data' => $data];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.categories.list.generation', (string) Str::uuid());
        Cache::forget('admin.options.categories');
    }

    public function show(string $id)
    {
        $collection = Collection::query()
            ->published()
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug->en', $id))
            ->first();

        if ($collection) {
            $slug = is_array($collection->slug) ? ($collection->slug['en'] ?? null) : $collection->slug;
            $slugAr = is_array($collection->slug) ? ($collection->slug['ar'] ?? null) : null;
            $memberCategories = $collection->categoriesFromMembers();

            return response()->json([
                'data' => [
                    'id' => $collection->id,
                    'name' => $collection->translated_name,
                    'slug' => $slug,
                    'slug_ar' => $slugAr,
                    'description' => $collection->description,
                    'image' => $collection->image_url,
                    'logo_url' => null,
                    'parent_id' => null,
                    'collection_id' => $collection->id,
                    'is_published' => true,
                    'position' => (int) $collection->position,
                    'children' => CategoryResource::collection($memberCategories)->resolve(),
                ],
            ]);
        }

        $category = Category::query()
            ->with(['children' => fn ($q) => $q->orderBy('position')])
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug->en', $id))
            ->firstOrFail();

        return new CategoryResource($category);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'name.ar' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'parent_id' => 'nullable|uuid',
            'path' => 'nullable|string',
        ]);

        $nameEn = $data['name']['en'];
        $nameAr = $data['name']['ar'] ?? $nameEn;

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => [
                'en' => $nameEn,
                'ar' => $nameAr,
            ],
            'slug' => [
                'en' => UniqueSlug::make(Category::class, $nameEn, 'slug', null, 'en', 'category'),
                'ar' => UniqueSlug::make(Category::class, $nameAr, 'slug', null, 'ar', 'category'),
            ],
            'description' => is_array($data['description'] ?? null)
                ? ($data['description']['en'] ?? null)
                : ($data['description'] ?? null),
            'parent_id' => $data['parent_id'] ?? null,
            'collection_id' => null,
            'path' => $data['path'] ?? null,
            // Homepage by default — matches admin create form + model attributes.
            'is_featured' => $request->has('is_featured')
                ? $request->boolean('is_featured')
                : true,
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|array',
            'description' => 'nullable|array',
            'parent_id' => 'nullable|uuid',
            'path' => 'nullable|string',
        ]);

        unset($data['slug'], $data['collection_id']);
        if (isset($data['description']) && is_array($data['description'])) {
            $data['description'] = $data['description']['en'] ?? null;
        }
        $data['collection_id'] = null;
        $cat->update($data);

        return new CategoryResource($cat->fresh('children'));
    }

    public function destroy(string $id)
    {
        $cat = Category::findOrFail($id);
        $cat->delete();

        return response()->json(null, 204);
    }
}
