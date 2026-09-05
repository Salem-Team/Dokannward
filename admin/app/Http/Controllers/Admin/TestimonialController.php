<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Support\TestimonialSources;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::query()
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        return view('admin.testimonials.create');
    }

    public function store(Request $request)
    {
        $testimonial = Testimonial::create($this->validatedPayload($request));

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', "“{$testimonial->name}” was added — it will show on the homepage when Active is on.");
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validatedPayload($request));

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', "“{$testimonial->name}” was updated.");
    }

    public function destroy(Testimonial $testimonial)
    {
        $name = $testimonial->name;
        $testimonial->delete();

        return redirect()
            ->route('admin.testimonials.index')
            ->with('success', "“{$name}” was deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        // Empty optional strings break `url` / checkbox quirks — normalize first.
        $request->merge([
            'title' => $this->blankToNull($request->input('title')),
            'company' => $this->blankToNull($request->input('company')),
            'avatar' => $this->blankToNull($request->input('avatar')),
            'source' => $this->blankToNull($request->input('source')),
            'display_order' => $request->input('display_order', 0) === '' || $request->input('display_order') === null
                ? 0
                : $request->input('display_order'),
            'rating' => $request->input('rating', 5),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'avatar' => ['nullable', 'url', 'max:500'],
            'source' => ['nullable', 'string', Rule::in(TestimonialSources::keys())],
            'rating' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'rating.required' => 'Please choose a star rating.',
            'rating.in' => 'Rating must be between 1 and 5 stars.',
            'avatar.url' => 'Avatar must be a full image URL (https://…).',
            'source.in' => 'Please choose a valid social platform.',
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['display_order'] = (int) ($validated['display_order'] ?? 0);
        $validated['rating'] = (int) $validated['rating'];

        return $validated;
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
