<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Services\StorefrontRevalidator;
use Illuminate\Http\Request;

/**
 * Moderation queue for customer product reviews. Every review submitted on
 * the storefront starts out unapproved — nothing reaches the public API
 * until an admin approves it here, so the catalog never shows spam or
 * unwanted content.
 */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = ProductReview::with(['product', 'user']);

        if ($status === 'pending') {
            $query->pending();
        } elseif ($status === 'approved') {
            $query->approved();
        }
        // 'all' → no filter

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->input('rating'));
        }

        $reviews = $query->latest()->paginate(20)->withQueryString();

        // One aggregate query instead of three separate COUNT(*) round trips
        // for the status tabs.
        $tally = ProductReview::selectRaw('COUNT(*) as all_count, SUM(approved = 0) as pending_count, SUM(approved = 1) as approved_count')->first();
        $counts = [
            'pending' => (int) $tally->pending_count,
            'approved' => (int) $tally->approved_count,
            'all' => (int) $tally->all_count,
        ];

        return view('admin.reviews.index', compact('reviews', 'counts', 'status'));
    }

    public function approve(string $id)
    {
        $review = ProductReview::with('product')->findOrFail($id);
        $review->update(['approved' => true]);
        ProductController::forgetListCache();

        $paths = ['/', '/collections', '/collections/all'];
        if ($review->product?->slug) {
            $paths[] = '/products/'.$review->product->slug;
        }
        StorefrontRevalidator::purge($paths, ['catalog', 'products']);

        return redirect()
            ->route('admin.reviews.index', request()->only('status'))
            ->with('success', 'Review approved — it is now visible on the storefront.');
    }

    public function reject(string $id)
    {
        ProductReview::findOrFail($id)->update(['approved' => false]);
        ProductController::forgetListCache();

        StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.reviews.index', request()->only('status'))
            ->with('success', 'Review moved back to pending.');
    }

    public function approveAll()
    {
        $count = ProductReview::pending()->update(['approved' => true]);
        ProductController::forgetListCache();

        StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.reviews.index', ['status' => 'approved'])
            ->with('success', "Approved {$count} pending review(s).");
    }

    public function destroy(string $id)
    {
        ProductReview::findOrFail($id)->delete();
        ProductController::forgetListCache();

        StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.reviews.index', request()->only('status'))
            ->with('success', 'Review deleted.');
    }
}
