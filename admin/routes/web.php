<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeskSearchController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PolicyController as AdminPolicyController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Admin\WebsiteController as AdminWebsiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin-only web routes
|--------------------------------------------------------------------------
|
| The public storefront lives in the Next.js app. This Laravel app is
| deliberately admin + API only — any leftover Blade shop that used to
| ship with the dashboard has been removed so visiting the root never
| dumps you onto the old Korfdya demo site.
|
*/

// Root always lands on the admin panel.
Route::redirect('/', '/admin')->name('home');

// Common mistaken paths (Safari/bookmarks often omit /admin).
Route::redirect('/dashboard', '/admin/dashboard');
Route::redirect('/orders', '/admin/orders');
Route::redirect('/products', '/admin/products');

// Default /login → admin login (used by auth middleware).
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Admin Authentication (guest)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.login');
    })->name('root');

    Route::get('/login', [AdminAuthController::class, 'showLogin'])
        ->name('login')
        ->middleware('guest');

        // Soft IP throttle: lockout (5 failures / 15 min) is the primary control.
        // Named limiter stays generous so deploy warm / retries never lock staff out
        // of the sign-in desk with a bare 429 page.
        Route::post('/login', [AdminAuthController::class, 'login'])
            ->name('login.post')
            ->middleware(['guest', 'throttle:admin-login']);

    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->name('logout')
        ->middleware('auth');
});

// Admin Routes (auth + staff gate required)
Route::middleware(['auth', 'admin', 'admin.locale', 'admin.nav.seen'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/language/switch', [App\Http\Controllers\AdminLanguageController::class, 'switch'])->name('language.switch');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/desk-search', [DeskSearchController::class, 'search'])->name('desk-search');

    Route::post('products/bulk-destroy', [AdminProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
    Route::post('products/{product}/images', [AdminProductController::class, 'storeImages'])->name('products.images.store');
    Route::resource('products', AdminProductController::class);
    Route::post('sizes', [\App\Http\Controllers\Admin\SizeController::class, 'store'])->name('sizes.store');
    Route::delete('products/images/{photo}', [AdminProductController::class, 'deleteImage'])->name('products.images.delete');
    Route::post('products/images/{photo}/primary', [AdminProductController::class, 'setPrimaryImage'])->name('products.images.primary');
    Route::post('products/{product}/color-photos/{photo}/primary', [AdminProductController::class, 'setColorPhotoPrimary'])->name('products.color-photos.primary');

    Route::resource('collections', AdminCollectionController::class)->except(['show']);
    Route::resource('categories', AdminCategoryController::class)->except(['show']);

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [AdminOrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/bulk-destroy', [AdminOrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');
    Route::get('/orders/catalog-search', [AdminOrderController::class, 'catalogSearch'])->name('orders.catalogSearch');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders/{id}/edit', [AdminOrderController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{id}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::delete('/orders/{id}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');

    Route::get('/orders/{orderId}/returns/create', [\App\Http\Controllers\Admin\OrderReturnController::class, 'create'])->name('orders.returns.create');
    Route::post('/orders/{orderId}/returns', [\App\Http\Controllers\Admin\OrderReturnController::class, 'store'])->name('orders.returns.store');
    Route::get('/orders/{orderId}/returns/{returnId}/credit-note', [\App\Http\Controllers\Admin\OrderReturnController::class, 'creditNote'])->name('orders.returns.creditNote');
    Route::delete('/orders/{orderId}/returns/{returnId}', [\App\Http\Controllers\Admin\OrderReturnController::class, 'destroy'])->name('orders.returns.destroy');

    Route::resource('customers', AdminCustomerController::class)->except(['show']);
    Route::resource('brands', AdminBrandController::class)->except(['show']);
    Route::resource('testimonials', AdminTestimonialController::class)->except(['show']);
    Route::resource('pages', AdminPageController::class)->except(['show']);
    Route::resource('banners', AdminBannerController::class)->except(['show']);

    Route::get('/policies', [AdminPolicyController::class, 'index'])->name('policies.index');
    Route::get('/policies/{slug}/edit', [AdminPolicyController::class, 'edit'])
        ->whereIn('slug', ['privacy-policy', 'terms-of-service', 'refund-policy', 'shipping-policy'])
        ->name('policies.edit');
    Route::put('/policies/{slug}', [AdminPolicyController::class, 'update'])
        ->whereIn('slug', ['privacy-policy', 'terms-of-service', 'refund-policy', 'shipping-policy'])
        ->name('policies.update');
    Route::post('/policies/{slug}/restore', [AdminPolicyController::class, 'restoreStarter'])
        ->whereIn('slug', ['privacy-policy', 'terms-of-service', 'refund-policy', 'shipping-policy'])
        ->name('policies.restore');

    Route::get('/website', [AdminWebsiteController::class, 'index'])->name('website.index');
    Route::get('/website/{section}', [AdminWebsiteController::class, 'edit'])
        ->whereIn('section', ['home', 'about', 'contact', 'nav', 'faq', 'footer'])
        ->name('website.edit');
    Route::put('/website/{section}', [AdminWebsiteController::class, 'update'])
        ->whereIn('section', ['home', 'about', 'contact', 'nav', 'faq', 'footer'])
        ->name('website.update');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/approve-all', [AdminReviewController::class, 'approveAll'])->name('reviews.approveAll');
    Route::post('/reviews/{id}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/reviews/{id}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{id}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::post('/contact-messages/{id}/toggle-read', [AdminContactMessageController::class, 'toggleRead'])->name('contact-messages.toggleRead');
    Route::delete('/contact-messages/{id}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

    Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/{id}/stock', [AdminInventoryController::class, 'updateStock'])->name('inventory.updateStock');

    Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
    Route::post('/notifications/mark-all-read', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::delete('/notifications/clear', [AdminNotificationController::class, 'clearAll'])->name('notifications.clearAll');
    Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::delete('/notifications/{id}', [AdminNotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [AdminProfileController::class, 'updatePassword'])
        ->name('profile.password')
        ->middleware('throttle:6,1');
});
