<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // Dashboard numbers don't need to be second-accurate, so the whole
        // payload is cached briefly — rebuilt at most once a minute.
        $data = Cache::remember('admin.dashboard.payload', now()->addMinutes(2), fn () => $this->buildDashboardData());

        return view('admin.dashboard', $data);
    }

    private function buildDashboardData(): array
    {
        $now = now();
        $today = $now->toDateString();
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // One aggregate pass for order KPIs instead of ~10 separate queries.
        $orderKpis = Order::query()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END) as total_revenue")
            ->selectRaw("AVG(CASE WHEN status != 'cancelled' THEN total_amount ELSE NULL END) as avg_order_value")
            ->selectRaw("SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as non_cancelled_orders")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders")
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as orders_today', [$today])
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = ? AND status != 'cancelled' THEN total_amount ELSE 0 END) as revenue_today", [$today])
            ->selectRaw("SUM(CASE WHEN created_at >= ? AND status != 'cancelled' THEN total_amount ELSE 0 END) as revenue_this_month", [$thisMonthStart])
            ->selectRaw("SUM(CASE WHEN created_at BETWEEN ? AND ? AND status != 'cancelled' THEN total_amount ELSE 0 END) as revenue_last_month", [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as orders_this_month', [$thisMonthStart])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_last_month', [$lastMonthStart, $lastMonthEnd])
            ->first();

        // Refunds reduce net revenue (issued date, not original order date).
        $refundKpis = OrderReturn::query()
            ->selectRaw('COALESCE(SUM(refund_amount), 0) as total_refunds')
            ->selectRaw('COALESCE(SUM(CASE WHEN DATE(COALESCE(refunded_at, created_at)) = ? THEN refund_amount ELSE 0 END), 0) as refunds_today', [$today])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(refunded_at, created_at) >= ? THEN refund_amount ELSE 0 END), 0) as refunds_this_month', [$thisMonthStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(refunded_at, created_at) BETWEEN ? AND ? THEN refund_amount ELSE 0 END), 0) as refunds_last_month', [$lastMonthStart, $lastMonthEnd])
            ->first();

        $productKpis = Product::query()
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as products_this_month', [$thisMonthStart])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as products_last_month', [$lastMonthStart, $lastMonthEnd])
            ->first();

        $customerKpis = User::query()
            ->selectRaw('COUNT(*) as total_customers')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as customers_this_month', [$thisMonthStart])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as customers_last_month', [$lastMonthStart, $lastMonthEnd])
            ->first();

        $grossRevenue = (float) ($orderKpis->total_revenue ?? 0);
        $totalRefunds = (float) ($refundKpis->total_refunds ?? 0);
        $totalRevenue = max(0, round($grossRevenue - $totalRefunds, 2));
        $totalOrders = (int) ($orderKpis->total_orders ?? 0);
        $totalProducts = (int) ($productKpis->total_products ?? 0);
        $totalCustomers = (int) ($customerKpis->total_customers ?? 0);
        $pendingOrders = (int) ($orderKpis->pending_orders ?? 0);
        $processingOrders = (int) ($orderKpis->processing_orders ?? 0);
        $ordersToday = (int) ($orderKpis->orders_today ?? 0);
        $revenueToday = max(0, round(
            (float) ($orderKpis->revenue_today ?? 0) - (float) ($refundKpis->refunds_today ?? 0),
            2
        ));
        $nonCancelledCount = (int) ($orderKpis->non_cancelled_orders ?? 0);
        $avgOrderValue = $nonCancelledCount > 0
            ? round($totalRevenue / $nonCancelledCount, 2)
            : 0.0;

        $recentOrders = Order::with(['user', 'shippingAddress'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $topProducts = Product::select([
                'products.id',
                'products.name',
                'products.slug',
                'products.price',
                'products.price_min',
                'products.price_max',
                'products.images',
                'products.created_at',
            ])
            ->selectRaw('COUNT(order_items.id) as sales_count')
            ->selectRaw('SUM(order_items.unit_price * order_items.qty) as revenue')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('order_items', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy([
                'products.id',
                'products.name',
                'products.slug',
                'products.price',
                'products.price_min',
                'products.price_max',
                'products.images',
                'products.created_at',
            ])
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();

        if ($topProducts->isEmpty()) {
            $topProducts = Product::with('photos')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    $product->sales_count = 0;
                    $product->revenue = 0;

                    return $product;
                });
        } else {
            $topProducts->load('photos');
        }

        $lowStockProducts = Product::with('photos')
            ->withSum('variants', 'stock')
            ->whereHas('variants', function ($query) {
                $query->where('stock', '<', 10);
            })
            ->limit(5)
            ->get()
            ->map(function ($product) {
                $product->stock_left = (int) ($product->variants_sum_stock ?? 0);

                return $product;
            });

        $rangeStart = $now->copy()->subMonths(11)->startOfMonth();

        $monthlyRevenue = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $rangeStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $monthlyRefunds = OrderReturn::query()
            ->whereRaw('COALESCE(refunded_at, created_at) >= ?', [$rangeStart])
            ->selectRaw("DATE_FORMAT(COALESCE(refunded_at, created_at), '%Y-%m') as ym, SUM(refund_amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $monthlyOrders = Order::where('created_at', '>=', $rangeStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $revenueChartLabels = [];
        $revenueChartData = [];
        $ordersChartLabels = [];
        $ordersChartData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $key = $date->format('Y-m');

            $revenueChartLabels[] = $date->format('M');
            $revenueChartData[] = round(
                max(0, (float) ($monthlyRevenue[$key] ?? 0) - (float) ($monthlyRefunds[$key] ?? 0)),
                2
            );

            $ordersChartLabels[] = $date->format('M');
            $ordersChartData[] = (int) ($monthlyOrders[$key] ?? 0);
        }

        $categorySales = Category::select('categories.name')
            ->selectRaw('COUNT(order_items.id) as sales_count')
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('order_items', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();

        if ($categorySales->isEmpty()) {
            $categorySales = Category::select('name')
                ->limit(5)
                ->get()
                ->map(function ($category) {
                    $category->sales_count = 0;

                    return $category;
                });
        }

        $categoryChartLabels = $categorySales->map(function ($category) {
            $name = $category->name;

            return is_array($name)
                ? ($name[app()->getLocale()] ?? $name['en'] ?? 'Category')
                : (string) $name;
        })->toArray();

        $categoryChartData = $categorySales->pluck('sales_count')->map(fn ($n) => (int) $n)->toArray();

        if (empty($categoryChartLabels)) {
            $categoryChartLabels = ['No sales yet'];
            $categoryChartData = [1];
        }

        $currencySymbol = trim(str_replace(
            number_format(0, 2),
            '',
            Money::format(0)
        )) ?: 'LE';

        return [
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'revenueTrend' => $this->trendMeta(
                max(0, (float) ($orderKpis->revenue_this_month ?? 0) - (float) ($refundKpis->refunds_this_month ?? 0)),
                max(0, (float) ($orderKpis->revenue_last_month ?? 0) - (float) ($refundKpis->refunds_last_month ?? 0))
            ),
            'ordersTrend' => $this->trendMeta(
                (int) ($orderKpis->orders_this_month ?? 0),
                (int) ($orderKpis->orders_last_month ?? 0)
            ),
            'productsTrend' => $this->trendMeta(
                (int) ($productKpis->products_this_month ?? 0),
                (int) ($productKpis->products_last_month ?? 0)
            ),
            'customersTrend' => $this->trendMeta(
                (int) ($customerKpis->customers_this_month ?? 0),
                (int) ($customerKpis->customers_last_month ?? 0)
            ),
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'ordersToday' => $ordersToday,
            'revenueToday' => $revenueToday,
            'avgOrderValue' => $avgOrderValue,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'revenueChartLabels' => $revenueChartLabels,
            'revenueChartData' => $revenueChartData,
            'ordersChartLabels' => $ordersChartLabels,
            'ordersChartData' => $ordersChartData,
            'categoryChartLabels' => $categoryChartLabels,
            'categoryChartData' => $categoryChartData,
            'currencySymbol' => $currencySymbol,
        ];
    }

    /**
     * @return array{direction: string, label: string, percent: float}
     */
    private function trendMeta(float|int $current, float|int $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous <= 0.0 && $current <= 0.0) {
            return [
                'direction' => 'neutral',
                'label' => '0.0%',
                'percent' => 0.0,
            ];
        }

        if ($previous <= 0.0) {
            return [
                'direction' => 'positive',
                'label' => 'New',
                'percent' => 100.0,
            ];
        }

        $percent = (($current - $previous) / $previous) * 100;
        $direction = $percent > 0.05 ? 'positive' : ($percent < -0.05 ? 'negative' : 'neutral');

        return [
            'direction' => $direction,
            'label' => ($percent >= 0 ? '+' : '').number_format($percent, 1).'%',
            'percent' => round($percent, 1),
        ];
    }
}
