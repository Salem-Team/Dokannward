<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public const LIST_CACHE_KEY = 'api.collections.tree';

    /**
     * Collections with categories derived from direct product members —
     * powers /collections and catalog filters.
     */
    public function index()
    {
        $generation = Cache::get('api.collections.list.generation') ?: 'v1';
        $payload = Cache::remember(self::LIST_CACHE_KEY.'.'.$generation.'.'.app()->getLocale(), now()->addMinutes(10), function () {
            $collections = Collection::query()
                ->published()
                ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('position')
                ->orderBy('name->en')
                ->get()
                ->each(fn (Collection $collection) => $collection->setRelation(
                    'categories',
                    $collection->categoriesFromMembers()
                ));

            return CollectionResource::collection($collections)->response()->getData(true);
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.collections.list.generation', (string) Str::uuid());
        Cache::forget('admin.options.collections');
        Cache::forget('admin.options.collection_choices');
        Cache::forget('admin.options.categories');
    }

    public function show(string $id)
    {
        $collection = Collection::query()
            ->published()
            ->withCount(['products' => fn ($q) => $q->where('status', 'active')])
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug->en', $id))
            ->firstOrFail();

        $collection->setRelation('categories', $collection->categoriesFromMembers());

        return new CollectionResource($collection);
    }
}
