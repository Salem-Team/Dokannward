<?php

namespace App\Http\Resources;

use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront-facing category payload. Slugs are English-primary (matching
 * product filters + /collections/{handle}). `image` is the homepage banner
 * cover (`path`); `logo_url` is the mark under the hero logo rail.
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slug = is_array($this->slug) ? ($this->slug['en'] ?? null) : $this->slug;
        $slugAr = is_array($this->slug) ? ($this->slug['ar'] ?? null) : null;
        $version = $this->updated_at;

        return [
            'id' => $this->id,
            'name' => $this->translated_name,
            'slug' => $slug,
            'slug_ar' => $slugAr,
            'description' => $this->description,
            'image' => PublicUrl::versioned($this->path, $version),
            'logo_url' => PublicUrl::versioned($this->logo_url, $version),
            'parent_id' => $this->parent_id,
            'collection_id' => $this->collection_id,
            'position' => (int) $this->position,
            'is_featured' => (bool) $this->is_featured,
            'children' => $this->whenLoaded('children', fn () => CategoryResource::collection(
                $this->children->sortBy('position')->values()
            )),
        ];
    }
}
