<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\HomepageSectionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront API (consumed by the Next.js Dokan Ward site)
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ProductReviewController::class, 'index']);
Route::post('/products/{id}/reviews', [ProductReviewController::class, 'store']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/collections', [CollectionController::class, 'index']);
Route::get('/collections/{id}', [CollectionController::class, 'show']);

Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{id}', [BrandController::class, 'show']);

Route::get('/catalog/handles', [CatalogController::class, 'handles']);

Route::get('/testimonials', [TestimonialController::class, 'index']);

Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{id}', [PageController::class, 'show']);

Route::get('/site-settings', [SiteSettingController::class, 'index']);
Route::get('/settings/currency', [SiteSettingController::class, 'currency']);
Route::get('/settings/store', [SiteSettingController::class, 'storefront']);
Route::get('/settings/checkout', [SiteSettingController::class, 'checkout']);
Route::get('/settings/inventory', [SiteSettingController::class, 'inventory']);
Route::get('/settings/content', [SiteSettingController::class, 'content']);

Route::get('/sliders', [SliderController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/homepage-sections', [HomepageSectionController::class, 'index']);

// Stateless checkout — the Next.js cart lives client-side and posts the
// whole payload (customer + items) in a single request.
Route::post('/checkout', [CheckoutController::class, 'store']);
Route::get('/checkout/{id}', [CheckoutController::class, 'show']);

Route::middleware(['throttle:geo'])
    ->withoutMiddleware('throttle:api')
    ->group(function () {
        Route::get('/geo/reverse', [GeoController::class, 'reverse']);
        Route::get('/geo/search', [GeoController::class, 'search']);
        Route::get('/geo/ip', [GeoController::class, 'ip']);
    });

// Admin-facing order management, reused by the storefront's own order lookups.
Route::get('/orders/{id}', [OrderController::class, 'show']);

// Contact page submissions — throttled to guard the admin inbox against spam.
Route::post('/contact', [ContactMessageController::class, 'store'])->middleware('throttle:5,1');
