<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $openBrandId = $request->query('open');

        $brands = Brand::query()
            ->with([
                'products' => function ($query) {
                    $query->with([
                        'category:id,name',
                        'photos' => fn ($photos) => $photos->orderByDesc('is_primary')->orderBy('position'),
                    ])
                        ->orderByDesc('featured')
                        ->orderBy('name->en');
                },
            ])
            ->withCount('products')
            ->orderBy('name->en')
            ->paginate(20)
            ->withQueryString();

        return view('admin.brands.index', compact('brands', 'openBrandId'));
    }

    public function create()
    {
        return view('admin.brands.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateBrand($request);

        Brand::create([
            'id' => (string) Str::uuid(),
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?? $validated['name_en'],
            ],
            'slug' => UniqueSlug::make(Brand::class, $validated['name_en'], 'slug', null, null, 'brand'),
            'description' => [
                'en' => $validated['description_en'] ?? '',
                'ar' => $validated['description_ar'] ?? $validated['description_en'] ?? '',
            ],
            'country' => $validated['country'] ?? null,
            'logo_url' => $this->resolveLogoUrl($request, $validated['name_en']),
        ]);

        return redirect()->route('admin.brands.index')->with('success', 'Brand created successfully.');
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);

        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $validated = $this->validateBrand($request);

        $brand->update([
            'name' => [
                'en' => $validated['name_en'],
                'ar' => $validated['name_ar'] ?? $validated['name_en'],
            ],
            'slug' => $brand->slug ?: UniqueSlug::make(Brand::class, $validated['name_en'], 'slug', $brand->id, null, 'brand'),
            'description' => [
                'en' => $validated['description_en'] ?? '',
                'ar' => $validated['description_ar'] ?? $validated['description_en'] ?? '',
            ],
            'country' => $validated['country'] ?? null,
            'logo_url' => $this->resolveLogoUrl($request, $validated['name_en'], $brand->logo_url),
        ]);

        return redirect()->route('admin.brands.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->products()->exists()) {
            $count = $brand->products()->count();

            return redirect()->route('admin.brands.index')
                ->with('error', "Reassign or delete {$count} product".($count === 1 ? '' : 's').' before removing this brand.');
        }

        if ($brand->logo_url && str_contains($brand->logo_url, '/storage/brands/')) {
            $old = Str::after($brand->logo_url, '/storage/');
            if ($old !== '' && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand deleted successfully.');
    }

    private function validateBrand(Request $request): array
    {
        return $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'country' => 'nullable|string|max:255',
            'logo_source' => ['nullable', Rule::in(['upload', 'url'])],
            'logo_file' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
            ],
            'logo_url' => ['nullable', 'url', 'max:1024'],
        ]);
    }

    /**
     * Prefer an uploaded high-quality logo file; otherwise an external URL;
     * otherwise keep the existing mark or fall back to a monochrome avatar.
     */
    private function resolveLogoUrl(Request $request, string $name, ?string $existing = null): string
    {
        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            $path = $file->store('brands', 'public');

            // Drop the previous local file when replacing a stored logo.
            if ($existing && str_contains($existing, '/storage/brands/')) {
                $old = Str::after($existing, '/storage/');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }

            return \App\Support\PublicUrl::storage($path);
        }

        if ($request->input('logo_source') === 'url' && $request->filled('logo_url')) {
            return $request->input('logo_url');
        }

        return $existing ?: $this->defaultLogoUrl($name);
    }

    private function defaultLogoUrl(string $name): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=0a0a0a&color=fff&size=512&bold=true';
    }
}
