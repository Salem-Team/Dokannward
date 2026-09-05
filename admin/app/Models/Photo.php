<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'imageable_type', 'imageable_id', 'storage_path', 'file_name', 'file_size', 'mime_type', 'width', 'height', 'alt_text', 'is_primary', 'position', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $appends = ['url'];

    /**
     * The one place that turns a stored relative path (e.g.
     * "products/foo.jpg") into a browsable URL. `storage_path` itself is
     * intentionally left as the raw value — code that deletes files from
     * disk (Storage::disk('public')->delete(...)) needs that raw path, not
     * a URL.
     */
    public function getUrlAttribute(): string
    {
        return (string) \App\Support\PublicUrl::versioned(
            \App\Support\PublicUrl::storage((string) $this->storage_path),
            $this->updated_at,
        );
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
