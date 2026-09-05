<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InventoryController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 10;

    private const MEDIUM_STOCK_THRESHOLD = 30;

    public function index(Request $request)
    {
        $stockStatus = $request->get('stock_status');
        if (! $stockStatus && $request->filled('low_stock')) {
            $stockStatus = 'low';
        }

        $query = ProductVariant::query()->with([
            'product.brand',
            'product.category',
            'product.photos',
            'color',
            'size',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('brand')) {
            $query->whereHas('product', fn ($q) => $q->where('brand_id', $request->brand));
        }

        if ($request->filled('category')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $request->category));
        }

        if ($stockStatus && $stockStatus !== 'all') {
            $this->applyStockStatusFilter($query, $stockStatus);
        }

        if ($request->get('reserved') === 'yes') {
            $query->where('stock_reserved', '>', 0);
        } elseif ($request->get('reserved') === 'no') {
            $query->where(function ($q) {
                $q->whereNull('stock_reserved')->orWhere('stock_reserved', 0);
            });
        }

        if ($request->get('availability') === 'unavailable') {
            $query->whereRaw('stock - COALESCE(stock_reserved, 0) <= 0');
        } elseif ($request->get('availability') === 'available') {
            $query->whereRaw('stock - COALESCE(stock_reserved, 0) > 0');
        }

        $this->applySort($query, $request->get('sort', 'stock_asc'));

        $variants = $query->paginate(50)->withQueryString();

        $pipeline = $this->stockPipeline();
        $stats = $this->inventoryStats();
        $categories = $this->categoryOptions();
        $brands = $this->brandOptions();

        $statusChips = [
            'all' => 'All SKUs',
            'out' => 'Out of stock',
            'low' => 'Low stock',
            'medium' => 'Medium',
            'healthy' => 'Healthy',
        ];

        return view('admin.inventory.index', compact(
            'variants',
            'pipeline',
            'stats',
            'categories',
            'brands',
            'statusChips',
            'stockStatus',
        ));
    }

    public function updateStock(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $validated = $request->validate([
            'stock' => 'required|integer|min:0',
        ]);

        $oldStock = $variant->stock;
        $variant->update(['stock' => $validated['stock']]);

        if ($validated['stock'] <= self::LOW_STOCK_THRESHOLD && $oldStock > self::LOW_STOCK_THRESHOLD) {
            NotificationService::lowStock($variant->product, $validated['stock']);
        }

        return redirect()->back()->with('success', 'Stock updated successfully.');
    }

    private function applyStockStatusFilter($query, string $status): void
    {
        match ($status) {
            'out' => $query->where('stock', 0),
            'low' => $query->whereBetween('stock', [1, self::LOW_STOCK_THRESHOLD]),
            'medium' => $query->whereBetween('stock', [self::LOW_STOCK_THRESHOLD + 1, self::MEDIUM_STOCK_THRESHOLD]),
            'healthy' => $query->where('stock', '>', self::MEDIUM_STOCK_THRESHOLD),
            default => null,
        };
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'stock_desc' => $query->orderBy('stock', 'desc'),
            'sku_asc' => $query->orderBy('sku'),
            'sku_desc' => $query->orderByDesc('sku'),
            'available_asc' => $query->orderByRaw('(stock - COALESCE(stock_reserved, 0)) ASC'),
            'available_desc' => $query->orderByRaw('(stock - COALESCE(stock_reserved, 0)) DESC'),
            default => $query->orderBy('stock', 'asc'),
        };
    }

    private function stockPipeline(): array
    {
        $counts = ProductVariant::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN stock BETWEEN 1 AND ? THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock BETWEEN ? AND ? THEN 1 ELSE 0 END) as medium_stock,
                SUM(CASE WHEN stock > ? THEN 1 ELSE 0 END) as healthy
            ', [
                self::LOW_STOCK_THRESHOLD,
                self::LOW_STOCK_THRESHOLD + 1,
                self::MEDIUM_STOCK_THRESHOLD,
                self::MEDIUM_STOCK_THRESHOLD,
            ])
            ->first();

        return [
            'all' => (int) ($counts->total ?? 0),
            'out' => (int) ($counts->out_of_stock ?? 0),
            'low' => (int) ($counts->low_stock ?? 0),
            'medium' => (int) ($counts->medium_stock ?? 0),
            'healthy' => (int) ($counts->healthy ?? 0),
        ];
    }

    private function inventoryStats(): array
    {
        $row = ProductVariant::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN stock BETWEEN 1 AND ? THEN 1 ELSE 0 END) as low_stock,
                SUM(COALESCE(stock_reserved, 0)) as reserved_units,
                SUM(stock) as total_units
            ', [self::LOW_STOCK_THRESHOLD])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'out_of_stock' => (int) ($row->out_of_stock ?? 0),
            'low_stock' => (int) ($row->low_stock ?? 0),
            'reserved_units' => (int) ($row->reserved_units ?? 0),
            'total_units' => (int) ($row->total_units ?? 0),
        ];
    }

    private function categoryOptions(): array
    {
        return Cache::remember('admin.options.categories', now()->addMinutes(15), function () {
            return Category::query()
                ->orderBy('name->en')
                ->get()
                ->mapWithKeys(fn (Category $category) => [
                    $category->id => $category->translated_name,
                ])
                ->all();
        });
    }

    private function brandOptions(): array
    {
        return Cache::remember('admin.options.brands', now()->addMinutes(15), function () {
            return Brand::query()
                ->orderBy('name->en')
                ->get()
                ->mapWithKeys(fn (Brand $brand) => [
                    $brand->id => $brand->translated_name,
                ])
                ->all();
        });
    }
}
