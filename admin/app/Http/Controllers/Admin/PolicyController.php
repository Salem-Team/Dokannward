<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HtmlSanitizer;
use App\Support\StorePolicies;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PolicyController extends Controller
{
    public function index()
    {
        $cards = StorePolicies::hubCards();
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');
        $faq = \App\Support\SiteContent::get('faq');
        $faqCount = count($faq['items'] ?? []);

        return view('admin.policies.index', compact('cards', 'storefrontBase', 'faqCount'));
    }

    public function edit(string $slug)
    {
        abort_unless(StorePolicies::isPolicySlug($slug), 404);

        $definition = StorePolicies::definition($slug);
        $page = StorePolicies::ensure($slug);
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.policies.edit', compact('definition', 'page', 'storefrontBase', 'slug'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(StorePolicies::isPolicySlug($slug), 404);

        $page = StorePolicies::ensure($slug);
        $definition = StorePolicies::definition($slug);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $published = $request->boolean('published');
        $content = HtmlSanitizer::clean($data['content'] ?? null);
        $hasBody = is_string($content) && trim(strip_tags($content)) !== '';

        // Published policies must have real body text — prevents an empty CMS
        // row from 404'ing the storefront while admin still shows a draft.
        if ($published && ! $hasBody) {
            throw ValidationException::withMessages([
                'content' => 'Add policy text before publishing, or turn Published off.',
            ]);
        }

        $page->update([
            'title' => $data['title'],
            'content' => $content,
            'meta_title' => $data['meta_title'] ?: ($definition['title'] ?? $data['title']),
            'meta_description' => $data['meta_description'] ?? null,
            'published' => $published,
            // Keep canonical slug fixed for legal policies.
            'slug' => $slug,
        ]);

        return redirect()
            ->route('admin.policies.edit', $slug)
            ->with('success', 'Policy saved — the live page now shows this exact text.');
    }

    public function restoreStarter(string $slug)
    {
        abort_unless(StorePolicies::isPolicySlug($slug), 404);

        $page = StorePolicies::ensure($slug);
        $definition = StorePolicies::definition($slug);

        $page->update([
            'title' => $definition['title'],
            'content' => HtmlSanitizer::clean(StorePolicies::starterHtml($slug)),
            'meta_title' => $definition['title'],
            'meta_description' => $definition['meta_description'],
            'published' => true,
            'slug' => $slug,
        ]);

        return redirect()
            ->route('admin.policies.edit', $slug)
            ->with('success', 'Starter policy text restored and published to the website.');
    }
}
