<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront-facing collection payload. English slug drives /collections/{handle}.
 */
class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slug = is_array($this->slug) ? ($this->slug['en'] ?? null) : $this->slug;
        $slugAr = is_array($this->slug) ? ($this->slug['ar'] ?? null) : null;

        return [
            'id' => $this->id,
            'name' => $this->translated_name,
            'slug' => $slug,
            'slug_ar' => $slugAr,
            'description' => $this->description,
            'image' => \App\Support\PublicUrl::versioned($this->image_url, $this->updated_at),
            'is_published' => (bool) $this->is_published,
            'position' => (int) $this->position,
            // Direct members, counted through `collection_product`.
            'products_count' => $this->when(
                $this->products_count !== null,
                fn () => (int) $this->products_count,
            ),
            'categories' => $this->whenLoaded('categories', fn () => CategoryResource::collection(
                $this->categories->sortBy('position')->values()
            )),
        ];
    }
}
