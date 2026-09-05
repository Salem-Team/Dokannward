<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public-facing reviews for a single product. Only ever exposes reviews an
 * admin has approved — everything submitted here starts out pending so the
 * storefront can't be used to post unmoderated content.
 */
class ProductReviewController extends Controller
{
    /** Accepts either the product's UUID or its storefront slug. */
    private function findProduct(string $id): Product
    {
        return Product::where('id', $id)->orWhere('slug', $id)->firstOrFail();
    }

    public function index(Request $request, string $id)
    {
        $page = max(1, (int) $request->input('page', 1));
        $generation = Cache::get('api.products.show.generation') ?: 'v1';
        $cacheKey = 'api.product.reviews.'.$generation.'.'.md5($id.'|'.$page);

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($id, $page) {
            $product = $this->findProduct($id);

            $reviews = $product->reviews()
                ->approved()
                ->latest()
                ->paginate(20, ['*'], 'page', $page);

            $items = $reviews->getCollection()->map(fn (ProductReview $r) => [
                'id' => $r->id,
                'author' => $r->author_name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'recommended' => $r->recommended,
                'created_at' => $r->created_at,
            ])->values()->all();

            return [
                'data' => $items,
                'meta' => [
                    'average' => $product->reviews_average,
                    'count' => $product->reviews_count,
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                ],
            ];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function store(Request $request, string $id)
    {
        $product = $this->findProduct($id);

        $data = $request->validate([
            'reviewer_name' => 'required|string|max:100',
            'reviewer_email' => 'nullable|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:2000',
            'recommended' => 'nullable|boolean',
        ]);

        $review = ProductReview::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'reviewer_name' => $data['reviewer_name'],
            'reviewer_email' => $data['reviewer_email'] ?? null,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'recommended' => $data['recommended'] ?? null,
            'approved' => false,
            'ip_address' => $request->ip(),
        ]);

        $review->loadMissing('product');
        NotificationService::newReview($review);

        return response()->json([
            'id' => $review->id,
            'message' => 'Thank you — your review has been submitted and will appear once approved by our team.',
        ], 201);
    }
}
