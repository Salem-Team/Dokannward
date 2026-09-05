<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::query()
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name->en')
            ->get();

        return view('admin.collections.index', compact('collections'));
    }

    public function create()
    {
        return view('admin.collections.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCollection($request);

        $nameAr = $validated['name_ar'] ?? $validated['name_en'];
        $slugEn = $this->resolveSlug($validated['slug_en'] ?? null, $validated['name_en'], 'en');
        $slugArSource = UniqueSlug::normalize((string) ($validated['slug_ar'] ?? '')) !== ''
            ? ($validated['slug_ar'] ?? '')
            : $slugEn;
        $slugAr = $this->resolveSlug($slugArSource, $nameAr !== '' ? $nameAr : $validated['name_en'], 'ar');

        Collection::create([
            'id' => (string) Str::uuid(),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $nameAr,
            ],
            'slug' => [
                'en' => $slugEn,
                'ar' => $slugAr,
            ],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? 0,
            'is_published' => $request->boolean('is_published'),
            'image_url' => $this->resolveImageUrl($request),
        ]);

        return redirect()->route('admin.collections.index')->with('success', 'Collection created successfully.');
    }

    public function edit($id)
    {
        $collection = Collection::withCount('products')
            ->with([
                'products' => fn ($q) => $q
                    ->with([
                        'category:id,name,slug',
                        'brand:id,name',
                        'photos' => fn ($photos) => $photos->orderByDesc('is_primary')->orderBy('position'),
                    ])
                    ->orderBy('collection_product.position'),
            ])
            ->findOrFail($id);

        return view('admin.collections.edit', compact('collection'));
    }

    public function update(Request $request, $id)
    {
        $collection = Collection::findOrFail($id);
        $validated = $this->validateCollection($request, $collection->id);

        $nameAr = $validated['name_ar'] ?? $validated['name_en'];
        $slugEn = $this->resolveSlug($validated['slug_en'] ?? null, $validated['name_en'], 'en', $collection->id);
        $slugArSource = UniqueSlug::normalize((string) ($validated['slug_ar'] ?? '')) !== ''
            ? ($validated['slug_ar'] ?? '')
            : $slugEn;
        $slugAr = $this->resolveSlug($slugArSource, $nameAr !== '' ? $nameAr : $validated['name_en'], 'ar', $collection->id);

        $collection->update([
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $nameAr,
            ],
            'slug' => [
                'en' => $slugEn,
                'ar' => $slugAr,
            ],
            'description' => $validated['description'] ?? null,
            'position' => $validated['position'] ?? 0,
            'is_published' => $request->boolean('is_published'),
            'image_url' => $this->resolveImageUrl($request, $collection->image_url),
        ]);

        return redirect()->route('admin.collections.index')->with('success', 'Collection updated successfully.');
    }

    public function destroy($id)
    {
        $collection = Collection::findOrFail($id);

        // Pivot rows cascade-delete; products and categories stay intact.
        if ($collection->image_url && str_contains($collection->image_url, '/storage/collections/')) {
            $old = Str::after($collection->image_url, '/storage/');
            if ($old !== '' && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $collection->delete();

        return redirect()->route('admin.collections.index')->with('success', 'Collection deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCollection(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'slug_en' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'slug_ar' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => 'nullable|string',
            'position' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'image_source' => ['nullable', Rule::in(['upload', 'url'])],
            'image_file' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
            ],
            'image_url' => ['nullable', 'url', 'max:1024'],
        ], [
            'slug_en.regex' => 'Use lowercase letters, numbers, and hyphens only (e.g. bags).',
            'slug_ar.regex' => 'Use lowercase letters, numbers, and hyphens only (e.g. bags).',
        ]);
    }

    private function resolveSlug(?string $candidate, string $name, string $locale, ?string $ignoreId = null): string
    {
        $normalized = UniqueSlug::normalize((string) $candidate);

        if ($normalized === '') {
            $normalized = UniqueSlug::normalize($name);
        }

        if ($normalized === '') {
            return UniqueSlug::make(Collection::class, $name, 'slug', $ignoreId, $locale, 'collection');
        }

        if (UniqueSlug::isTaken(Collection::class, $normalized, 'slug', $ignoreId, $locale)) {
            throw ValidationException::withMessages([
                "slug_{$locale}" => 'This slug is already in use. Choose another or regenerate from the name.',
            ]);
        }

        return $normalized;
    }

    private function resolveImageUrl(Request $request, ?string $existing = null): ?string
    {
        if ($request->input('remove_image') === '1' && ! $request->hasFile('image_file') && ! $request->filled('image_url')) {
            if ($existing && str_contains($existing, '/storage/collections/')) {
                $old = Str::after($existing, '/storage/');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }

            return null;
        }

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $path = $file->store('collections', 'public');

            if ($existing && str_contains($existing, '/storage/collections/')) {
                $old = Str::after($existing, '/storage/');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }

            return \App\Support\PublicUrl::storage($path);
        }

        if ($request->input('image_source') === 'url' && $request->filled('image_url')) {
            return $request->input('image_url');
        }

        return $existing;
    }
}
