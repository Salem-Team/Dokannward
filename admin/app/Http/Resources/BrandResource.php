<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront-facing brand payload — flat name/slug/logo for collection tiles.
 */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $description = $this->description;
        if (is_array($description)) {
            $description = $description[app()->getLocale()]
                ?? $description['en']
                ?? reset($description)
                ?: null;
        }

        return [
            'id' => $this->id,
            'name' => $this->translated_name,
            'slug' => $this->slug,
            'logo_url' => \App\Support\PublicUrl::versioned($this->logo_url, $this->updated_at),
            'country' => $this->country,
            'description' => $description,
        ];
    }
}
