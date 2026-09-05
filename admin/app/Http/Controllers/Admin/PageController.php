<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\HtmlSanitizer;
use App\Support\StorePolicies;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index()
    {
        // Legal policies have their own studio — keep this list for custom pages only.
        $pages = Page::query()
            ->whereNotIn('slug', StorePolicies::slugs())
            ->orderBy('position')
            ->orderBy('title')
            ->paginate(20);

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'position' => 'nullable|integer|min:0',
        ]);

        $slugSource = $data['slug'] ?: $data['title'];
        $slug = UniqueSlug::make(Page::class, $slugSource, 'slug', null, null, 'page');

        if (StorePolicies::isPolicySlug($slug)) {
            return redirect()
                ->route('admin.policies.edit', $slug)
                ->with('error', 'That address is reserved for a legal policy. Edit it under Policies.');
        }

        Page::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'title' => $data['title'],
            'content' => HtmlSanitizer::clean($data['content'] ?? null),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'position' => $data['position'] ?? 0,
            'published' => $request->boolean('published'),
        ]);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page created — it will appear on the website when published.');
    }

    public function edit(string $id)
    {
        $page = Page::findOrFail($id);

        if (StorePolicies::isPolicySlug((string) $page->slug)) {
            return redirect()
                ->route('admin.policies.edit', $page->slug)
                ->with('info', 'Legal policies are edited only under Policies — one editor, one live page.');
        }

        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, string $id)
    {
        $page = Page::findOrFail($id);

        if (StorePolicies::isPolicySlug((string) $page->slug)) {
            return redirect()
                ->route('admin.policies.edit', $page->slug)
                ->with('error', 'Legal policies can only be edited under Policies.');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'position' => 'nullable|integer|min:0',
        ]);

        $slug = UniqueSlug::normalize($data['slug']) ?: $page->slug;
        if (StorePolicies::isPolicySlug($slug)) {
            return back()
                ->withInput()
                ->withErrors(['slug' => 'This address is reserved for a legal policy. Pick another slug.']);
        }

        if (UniqueSlug::isTaken(Page::class, $slug, 'slug', $page->id)) {
            $slug = UniqueSlug::make(Page::class, $slug, 'slug', $page->id, null, 'page');
        }

        $page->update([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => HtmlSanitizer::clean($data['content'] ?? null),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'position' => $data['position'] ?? 0,
            'published' => $request->boolean('published'),
        ]);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page updated — changes will show on the website shortly.');
    }

    public function destroy(string $id)
    {
        $page = Page::findOrFail($id);

        if (StorePolicies::isPolicySlug((string) $page->slug)) {
            return redirect()
                ->route('admin.policies.index')
                ->with('error', 'Legal policies cannot be deleted. Edit or unpublish them under Policies.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page deleted.');
    }
}
