<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\SiteSettingController as ApiSiteSettingController;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\StorefrontRevalidator;
use App\Support\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebsiteController extends Controller
{
    public function index()
    {
        $pagesCount = Page::count();
        $publishedPages = Page::where('published', true)->count();
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.website.index', compact('pagesCount', 'publishedPages', 'storefrontBase'));
    }

    public function edit(string $section)
    {
        abort_unless(isset(SiteContent::KEYS[$section]), 404);

        $content = SiteContent::get($section);
        $linkPresets = SiteContent::linkPresets();
        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view("admin.website.{$section}", compact('section', 'content', 'linkPresets', 'storefrontBase'));
    }

    public function update(Request $request, string $section)
    {
        abort_unless(isset(SiteContent::KEYS[$section]), 404);

        $value = match ($section) {
            'home' => $this->validateHome($request),
            'about' => $this->validateAbout($request),
            'contact' => $this->validateContact($request),
            'nav' => $this->validateNav($request),
            'faq' => $this->validateFaq($request),
            'footer' => $this->validateFooter($request),
            default => [],
        };

        SiteContent::put($section, $value);
        Cache::forget(ApiSiteSettingController::CONTENT_CACHE_KEY);
        StorefrontRevalidator::purgeContent();

        return redirect()
            ->route('admin.website.edit', $section)
            ->with('success', 'Saved — your website will update shortly.');
    }

    private function validateHome(Request $request): array
    {
        return $request->validate([
            'hero_image' => 'nullable|string|max:500',
            'hero_wordmark' => 'nullable|string|max:500',
            'hero_alt' => 'nullable|string|max:160',
            'arrivals_eyebrow' => 'nullable|string|max:80',
            'arrivals_title' => 'nullable|string|max:120',
            'arrivals_link_label' => 'nullable|string|max:60',
            'testimonials_eyebrow' => 'nullable|string|max:80',
            'testimonials_title' => 'nullable|string|max:120',
        ]);
    }

    private function validateContact(Request $request): array
    {
        return $request->validate([
            'eyebrow' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:120',
            'lede' => 'nullable|string|max:500',
            'form_title' => 'nullable|string|max:120',
            'form_button' => 'nullable|string|max:60',
        ]);
    }

    private function validateNav(Request $request): array
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:12',
            'items.*.label' => 'required|string|max:60',
            'items.*.href' => 'required|string|max:255',
        ]);

        $items = [];
        foreach ($data['items'] as $item) {
            $label = trim($item['label']);
            $href = $this->normalizeHref($item['href']);
            if ($label === '' || $href === '') {
                continue;
            }
            $items[] = ['label' => $label, 'href' => $href];
        }

        if ($items === []) {
            return SiteContent::defaults()['nav'];
        }

        return ['items' => $items];
    }

    private function validateFaq(Request $request): array
    {
        $data = $request->validate([
            'eyebrow' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:120',
            'items' => 'nullable|array|max:20',
            'items.*.q' => 'nullable|string|max:255',
            'items.*.a' => 'nullable|string|max:2000',
            'items.*.tag' => 'nullable|string|max:40',
        ]);

        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            $tag = trim((string) ($item['tag'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $row = ['q' => $q, 'a' => $a];
            if ($tag !== '') {
                $row['tag'] = $tag;
            }
            $items[] = $row;
        }

        return [
            'eyebrow' => trim((string) ($data['eyebrow'] ?? '')) ?: 'Support',
            'title' => trim((string) ($data['title'] ?? '')) ?: 'Our Policies',
            'items' => $items,
        ];
    }

    private function validateFooter(Request $request): array
    {
        $data = $request->validate([
            'trust' => 'nullable|array|max:6',
            'trust.*' => 'nullable|string|max:80',
            'customer_care' => 'nullable|array|max:8',
            'customer_care.*.label' => 'nullable|string|max:80',
            'customer_care.*.href' => 'nullable|string|max:255',
            'information' => 'nullable|array|max:8',
            'information.*.label' => 'nullable|string|max:80',
            'information.*.href' => 'nullable|string|max:255',
        ]);

        $trust = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $data['trust'] ?? []
        )));

        return [
            'trust' => $trust,
            'customer_care' => $this->cleanLinkRows($data['customer_care'] ?? []),
            'information' => $this->cleanLinkRows($data['information'] ?? []),
        ];
    }

    private function validateAbout(Request $request): array
    {
        $data = $request->validate([
            'hero_eyebrow' => 'nullable|string|max:80',
            'hero_title' => 'nullable|string|max:200',
            'hero_subtitle' => 'nullable|string|max:500',
            'hero_image' => 'nullable|string|max:500',
            'story_eyebrow' => 'nullable|string|max:80',
            'story_title' => 'nullable|string|max:200',
            'story_image' => 'nullable|string|max:500',
            'story_paragraphs_text' => 'nullable|string|max:8000',
            'quote' => 'nullable|string|max:500',
            'quote_attribution' => 'nullable|string|max:80',
            'pillars_eyebrow' => 'nullable|string|max:80',
            'pillars_title' => 'nullable|string|max:200',
            'pillars' => 'nullable|array|max:6',
            'pillars.*.title' => 'nullable|string|max:80',
            'pillars.*.text' => 'nullable|string|max:500',
            'journey_eyebrow' => 'nullable|string|max:80',
            'journey_title' => 'nullable|string|max:200',
            'journey' => 'nullable|array|max:6',
            'journey.*.title' => 'nullable|string|max:80',
            'journey.*.text' => 'nullable|string|max:500',
            'edit_eyebrow' => 'nullable|string|max:80',
            'edit_title' => 'nullable|string|max:200',
            'edit_text' => 'nullable|string|max:1000',
            'edit_button_label' => 'nullable|string|max:60',
            'edit_button_href' => 'nullable|string|max:255',
            'edit_image' => 'nullable|string|max:500',
            'cta_eyebrow' => 'nullable|string|max:80',
            'cta_primary_label' => 'nullable|string|max:60',
            'cta_secondary_label' => 'nullable|string|max:60',
        ]);

        $paragraphs = preg_split("/\n\s*\n/", (string) ($data['story_paragraphs_text'] ?? '')) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        $pillars = [];
        foreach ($data['pillars'] ?? [] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $pillars[] = ['title' => $title, 'text' => $text];
        }

        $journey = [];
        foreach ($data['journey'] ?? [] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $journey[] = ['title' => $title, 'text' => $text];
        }

        unset($data['story_paragraphs_text'], $data['pillars'], $data['journey']);

        return array_merge($data, [
            'story_paragraphs' => $paragraphs,
            'pillars' => $pillars,
            'journey' => $journey,
            'edit_button_href' => $this->normalizeHref($data['edit_button_href'] ?? '/collections'),
        ]);
    }

    /** @param  list<array{label?: string, href?: string}>  $rows */
    private function cleanLinkRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $href = $this->normalizeHref((string) ($row['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $out[] = ['label' => $label, 'href' => $href];
        }

        return $out;
    }

    private function normalizeHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return $href;
        }
        if (! str_starts_with($href, '/')) {
            $href = '/'.$href;
        }

        return $href;
    }
}
