<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $openCategoryId = $request->query('open');

        $categories = Category::query()
            ->with([
                'products' => function ($query) {
                    $query->with([
                        'brand',
                        'photos' => fn ($photos) => $photos->orderByDesc('is_primary')->orderBy('position'),
                    ])
                        ->orderByDesc('featured')
                        ->orderBy('name->en');
                },
            ])
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name->en')
            ->get();

        return view('admin.categories.index', compact(
            'categories',
            'openCategoryId',
        ));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);

        $nameAr = $validated['name_ar'] ?? $validated['name_en'];
        $slugEn = $this->resolveSlug($validated['slug_en'] ?? null, $validated['name_en'], 'en');
        $slugArSource = UniqueSlug::normalize((string) ($validated['slug_ar'] ?? '')) !== ''
            ? ($validated['slug_ar'] ?? '')
            : $slugEn;
        $slugAr = $this->resolveSlug($slugArSource, $nameAr !== '' ? $nameAr : $validated['name_en'], 'ar');

        Category::create([
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
            // Categories are classification only — never tied to a collection.
            'collection_id' => null,
            'parent_id' => null,
            'position' => $validated['position'] ?? 0,
            'is_featured' => $request->has('is_featured')
                ? $request->boolean('is_featured')
                : true,
            'path' => $this->resolveImagePath($request),
            'logo_url' => $this->resolveLogoUrl($request),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);

        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $validated = $this->validateCategory($request, $category->id);

        $nameAr = $validated['name_ar'] ?? $validated['name_en'];
        $slugEn = $this->resolveSlug($validated['slug_en'] ?? null, $validated['name_en'], 'en', $category->id);
        $slugArSource = UniqueSlug::normalize((string) ($validated['slug_ar'] ?? '')) !== ''
            ? ($validated['slug_ar'] ?? '')
            : $slugEn;
        $slugAr = $this->resolveSlug($slugArSource, $nameAr !== '' ? $nameAr : $validated['name_en'], 'ar', $category->id);

        $category->update([
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $nameAr,
            ],
            'slug' => [
                'en' => $slugEn,
                'ar' => $slugAr,
            ],
            'description' => $validated['description'] ?? null,
            'collection_id' => null,
            'parent_id' => null,
            'position' => $validated['position'] ?? 0,
            // Absent field keeps homepage visibility (never silently drop Featured).
            'is_featured' => $request->has('is_featured')
                ? $request->boolean('is_featured')
                : true,
            'path' => $this->resolveImagePath($request, $category->path),
            'logo_url' => $this->resolveLogoUrl($request, $category->logo_url),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        $back = redirect()->route('admin.categories.index');

        $productCount = $category->products()->count();
        $uncategorize = request()->boolean('uncategorize_products');

        if ($productCount > 0 && ! $uncategorize) {
            return $back->with(
                'error',
                "This category has {$productCount} product".($productCount === 1 ? '' : 's').'. Confirm “Leave products without a category” to delete it safely.',
            );
        }

        if ($productCount > 0) {
            $category->products()->update(['category_id' => null]);
        }

        $this->deleteStoredAsset($category->path, 'categories');
        $this->deleteStoredAsset($category->logo_url, 'category-logos');

        $category->delete();

        $message = 'Category deleted successfully.';
        if ($productCount > 0) {
            $message = "Category deleted. {$productCount} product".($productCount === 1 ? ' is' : 's are').' now uncategorized — reassign anytime from Products.';
        }

        return $back->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'slug_en' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'slug_ar' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => 'nullable|string',
            'position' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'image_source' => ['nullable', Rule::in(['upload', 'url'])],
            'image_file' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
            ],
            'image_url' => ['nullable', 'url', 'max:1024'],
            'logo_source' => ['nullable', Rule::in(['upload', 'url'])],
            'logo_file' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
            ],
            'logo_url' => ['nullable', 'url', 'max:1024'],
        ], [
            'slug_en.regex' => 'Use lowercase letters, numbers, and hyphens only (e.g. mini-bag).',
            'slug_ar.regex' => 'Use lowercase letters, numbers, and hyphens only (e.g. mini-bag).',
        ]);
    }

    private function resolveSlug(?string $candidate, string $name, string $locale, ?string $ignoreId = null): string
    {
        $normalized = UniqueSlug::normalize((string) $candidate);

        if ($normalized === '') {
            $normalized = UniqueSlug::normalize($name);
        }

        if ($normalized === '') {
            return UniqueSlug::make(Category::class, $name, 'slug', $ignoreId, $locale, 'category');
        }

        if (UniqueSlug::isTaken(Category::class, $normalized, 'slug', $ignoreId, $locale)) {
            throw ValidationException::withMessages([
                "slug_{$locale}" => 'This slug is already in use. Choose another or regenerate from the name.',
            ]);
        }

        return $normalized;
    }

    private function resolveImagePath(Request $request, ?string $existing = null): ?string
    {
        if ($request->input('remove_image') === '1' && ! $request->hasFile('image_file') && ! $request->filled('image_url')) {
            $this->deleteStoredAsset($existing, 'categories');

            return null;
        }

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $path = $file->store('categories', 'public');
            $this->deleteStoredAsset($existing, 'categories');

            return \App\Support\PublicUrl::storage($path);
        }

        if ($request->input('image_source') === 'url' && $request->filled('image_url')) {
            return $request->input('image_url');
        }

        return $existing;
    }

    /**
     * Homepage logo rail mark — optional; categories without a logo stay off the rail.
     */
    private function resolveLogoUrl(Request $request, ?string $existing = null): ?string
    {
        if ($request->input('remove_logo') === '1' && ! $request->hasFile('logo_file') && ! $request->filled('logo_url')) {
            $this->deleteStoredAsset($existing, 'category-logos');

            return null;
        }

        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            $path = $file->store('category-logos', 'public');
            $this->deleteStoredAsset($existing, 'category-logos');

            return \App\Support\PublicUrl::storage($path);
        }

        if ($request->input('logo_source') === 'url' && $request->filled('logo_url')) {
            return $request->input('logo_url');
        }

        return $existing;
    }

    private function deleteStoredAsset(?string $url, string $folder): void
    {
        if (! $url || ! str_contains($url, '/storage/'.$folder.'/')) {
            return;
        }

        $old = Str::after($url, '/storage/');
        if ($old !== '' && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }
    }
}
