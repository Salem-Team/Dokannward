<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::query()
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->paginate(40);

        $all = Banner::query()->get(['id', 'is_active', 'start_at', 'end_at']);
        $liveCount = 0;
        $scheduledCount = 0;
        $expiredCount = 0;
        $offCount = 0;

        foreach ($all as $banner) {
            match ($banner->liveStatus()) {
                'live' => $liveCount++,
                'scheduled' => $scheduledCount++,
                'expired' => $expiredCount++,
                default => $offCount++,
            };
        }

        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.banners.index', compact(
            'banners',
            'liveCount',
            'scheduledCount',
            'expiredCount',
            'offCount',
            'storefrontBase',
        ));
    }

    public function create()
    {
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.banners.create', compact('storefrontBase'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_url'] = $this->resolveImageUrl($request);

        Banner::create(array_merge($data, ['id' => (string) Str::uuid()]));

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created — it syncs to the homepage when live.');
    }

    public function edit(string $id)
    {
        $banner = Banner::findOrFail($id);
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.banners.edit', compact('banner', 'storefrontBase'));
    }

    public function update(Request $request, string $id)
    {
        $banner = Banner::findOrFail($id);
        $data = $this->validated($request);
        $data['image_url'] = $this->resolveImageUrl($request, $banner->image_url);
        $banner->update($data);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated — homepage refreshed on the storefront.');
    }

    public function destroy(string $id)
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image_url && str_contains($banner->image_url, '/storage/banners/')) {
            $old = Str::after($banner->image_url, '/storage/');
            if ($old !== '' && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:1000',
            'button_text' => 'nullable|string|max:100',
            'button_url' => ['nullable', 'string', 'max:1024', $this->storefrontPathOrUrl()],
            'image_source' => ['nullable', Rule::in(['upload', 'url'])],
            'image_file' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif',
            ],
            'image_url' => ['nullable', 'string', 'max:1024', $this->storefrontPathOrUrl()],
            'position' => 'nullable|integer|min:0|max:99',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'remove_image' => 'nullable|in:0,1',
        ]);

        $data['position'] = (int) ($data['position'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['image_source'], $data['image_file'], $data['image_url'], $data['remove_image']);

        return $data;
    }

    private function storefrontPathOrUrl(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $value = trim((string) $value);
            if (str_starts_with($value, '/')) {
                return;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) {
                return;
            }

            $fail('Use a site path like /collections/all or a full http(s) URL.');
        };
    }

    private function resolveImageUrl(Request $request, ?string $existing = null): ?string
    {
        if ($request->input('remove_image') === '1' && ! $request->hasFile('image_file') && ! $request->filled('image_url')) {
            if ($existing && str_contains($existing, '/storage/banners/')) {
                $old = Str::after($existing, '/storage/');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }

            return null;
        }

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $path = $file->store('banners', 'public');

            if ($existing && str_contains($existing, '/storage/banners/')) {
                $old = Str::after($existing, '/storage/');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }

            return PublicUrl::storage($path);
        }

        if ($request->input('image_source') === 'url' && $request->filled('image_url')) {
            return trim((string) $request->input('image_url'));
        }

        // Keep existing when editing without touching the image controls.
        if ($request->filled('image_url') && $request->input('image_source') !== 'upload') {
            return trim((string) $request->input('image_url'));
        }

        return $existing;
    }
}
