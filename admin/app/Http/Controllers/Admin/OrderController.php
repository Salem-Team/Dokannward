<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\OrderLifecycle;
use App\Services\OrderPlacementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private OrderPlacementService $orders,
        private OrderLifecycle $lifecycle,
    ) {}

    public function index(Request $request)
    {
        $query = $this->filteredOrdersQuery($request)
            ->with([
                'user',
                'shippingAddress',
                'items.variant.color',
                'items.variant.photos',
                'items.product.photos' => fn ($photos) => $photos->orderByDesc('is_primary')->orderBy('position'),
            ])
            ->withSum('returns as refunded_total', 'refund_amount')
            ->withCount(['returns', 'items']);

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pipeline = [
            'all' => (int) $statusCounts->sum(),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'processing' => (int) ($statusCounts['processing'] ?? 0),
            'shipped' => (int) ($statusCounts['shipped'] ?? 0),
            'delivered' => (int) ($statusCounts['delivered'] ?? 0),
            'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            'refunded' => (int) ($statusCounts['refunded'] ?? 0),
        ];

        $openOrderId = $request->query('open');

        return view('admin.orders.index', compact('orders', 'pipeline', 'openOrderId'));
    }

    public function export(Request $request)
    {
        $orders = $this->filteredOrdersQuery($request)
            ->with(['user', 'shippingAddress', 'items'])
            ->withSum('returns as refunded_total', 'refund_amount')
            ->orderByDesc('created_at')
            ->get();

        $filename = 'dokan-ward-orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Order',
                'Status',
                'Client',
                'Phone',
                'Email',
                'City',
                'Items',
                'Subtotal',
                'Shipping',
                'Tax',
                'Total',
                'Refunded',
                'Placed at',
            ]);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->order_number,
                    $order->status,
                    $order->customerName(),
                    $order->customerPhone(),
                    $order->customerEmail(),
                    $order->shippingAddress?->city,
                    $order->items->sum('qty'),
                    number_format((float) $order->subtotal, 2, '.', ''),
                    number_format((float) $order->shipping_amount, 2, '.', ''),
                    number_format((float) $order->tax_amount, 2, '.', ''),
                    number_format((float) $order->total_amount, 2, '.', ''),
                    number_format((float) ($order->refunded_total ?? 0), 2, '.', ''),
                    optional($order->placed_at ?? $order->created_at)?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filteredOrdersQuery(Request $request)
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->input('refund') === 'partial') {
            $query->whereHas('returns')
                ->whereRaw('(SELECT COALESCE(SUM(refund_amount), 0) FROM order_returns WHERE order_returns.order_id = orders.id) < orders.total_amount');
        } elseif ($request->input('refund') === 'full') {
            $query->whereRaw('(SELECT COALESCE(SUM(refund_amount), 0) FROM order_returns WHERE order_returns.order_id = orders.id) >= orders.total_amount')
                ->whereHas('returns');
        } elseif ($request->input('refund') === 'any') {
            $query->whereHas('returns');
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';

            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    })
                    ->orWhereHas('shippingAddress', function ($addressQuery) use ($term) {
                        $addressQuery->where('recipient_name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('city', 'like', $term);
                    });
            });
        }

        return $query;
    }

    public function create()
    {
        $shipping = array_merge(
            SettingsController::DEFAULTS['shipping'],
            SiteSetting::where('key', 'shipping')->first()?->value ?? []
        );
        $tax = array_merge(
            SettingsController::DEFAULTS['tax'],
            SiteSetting::where('key', 'tax')->first()?->value ?? []
        );
        $currency = array_merge(
            SettingsController::DEFAULTS['currency'],
            SiteSetting::where('key', 'currency')->first()?->value ?? []
        );

        $payments = \App\Services\OrderPlacementService::paymentOptions();

        return view('admin.orders.create', [
            'searchUrl' => route('admin.orders.catalogSearch'),
            'paymentMethods' => $payments['methods'],
            'defaultPaymentMethod' => $payments['default_method'],
            'quote' => [
                'standard_shipping_fee' => (float) ($shipping['standard_shipping_fee'] ?? 0),
                'tax_rate' => (float) ($tax['tax_rate'] ?? 0),
                'tax_enabled' => (bool) ($tax['tax_enabled'] ?? false),
                'currency_symbol' => (string) ($currency['symbol'] ?? 'LE'),
                'currency_position' => (string) ($currency['position'] ?? 'before'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:255',
            'line_1' => 'required|string|max:500',
            'line_2' => 'nullable|string|max:500',
            'city' => 'required|string|max:120',
            'postal_code' => 'nullable|string|max:40',
            'country' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'nullable|string|max:40',
            'status' => 'required|in:pending,processing',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.variant_id' => 'nullable|string',
            'items.*.name' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1|max:99',
            'items.*.sku' => 'nullable|string|max:100',
        ]);

        try {
            $order = $this->orders->place($data);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()
            ->route('admin.orders.show', $order->id)
            ->with('success', 'Order '.$order->order_number.' created — stock reserved from inventory.');
    }

    /** JSON product picker for the create-order desk. */
    public function catalogSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $like = '%'.$q.'%';

        $products = Product::query()
            ->with(['variants' => function ($query) {
                $query->where('is_active', true)
                    ->with('color:id,name')
                    ->orderBy('sku');
            }])
            ->where('status', 'active')
            ->where(function ($query) use ($like) {
                $query->where('sku', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('name->en', 'like', $like)
                    ->orWhere('name->ar', 'like', $like);
            })
            ->orderBy('sku')
            ->limit(12)
            ->get(['id', 'sku', 'name', 'price', 'in_stock', 'status']);

        $data = $products->map(function (Product $product) {
            $variants = $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'stock' => (int) $variant->stock,
                'label' => $variant->color?->name
                    ?: ($variant->sku ?: 'Variant'),
            ])->values();

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->translated_name,
                'price' => (float) $product->price,
                'in_stock' => (bool) $product->in_stock,
                'variants' => $variants,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.variant.color',
            'items.variant.photos',
            'items.product.photos',
            'shippingAddress',
            'billingAddress',
            'returns.items.orderItem',
            'returns.processedBy',
        ])->findOrFail($id);

        $refundedTotal = (float) $order->returns->sum('refund_amount');
        $refundableRemaining = max(0, round((float) $order->total_amount - $refundedTotal, 2));

        return view('admin.orders.show', compact('order', 'refundedTotal', 'refundableRemaining'));
    }

    public function invoice($id)
    {
        $order = Order::with(['user', 'items.variant.color', 'shippingAddress', 'billingAddress'])
            ->findOrFail($id);

        $filename = 'invoice-'.$order->order_number.'.pdf';

        $pdf = Pdf::loadView('admin.orders.invoice', compact('order'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($filename);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', OrderLifecycle::STATUSES),
        ]);

        $this->lifecycle->apply($order, [
            'status' => $validated['status'],
        ], auth()->id());

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }

    public function edit($id)
    {
        $order = Order::with(['user', 'items', 'shippingAddress', 'billingAddress'])->findOrFail($id);

        return view('admin.orders.edit', compact('order'));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', OrderLifecycle::STATUSES),
            'notes' => 'nullable|string',
            'delivery_tracking_number' => 'nullable|string|max:100',
            'restock' => 'nullable|boolean',
        ]);

        $this->lifecycle->apply($order, [
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'delivery_tracking_number' => $validated['delivery_tracking_number'] ?? null,
            'restock' => $request->boolean('restock', true),
        ], auth()->id());

        return redirect()->route('admin.orders.show', $id)->with('success', 'Order updated successfully.');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            /** @var Order $order */
            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($id);

            // Return reserved stock unless inventory was already released
            // (cancel restock or refund credit-note restock).
            if (! in_array($order->status, ['cancelled', 'refunded'], true)) {
                $this->lifecycle->restockItems($order);
            }

            $order->delete();
        });

        return redirect()->route('admin.orders.index')->with('success', 'Order deleted successfully.');
    }

    /**
     * Delete many orders from the desk selection.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|uuid|exists:orders,id',
        ]);

        $ids = array_values(array_unique($validated['ids']));
        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted) {
            $orders = Order::query()
                ->with('items')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                if (! in_array($order->status, ['cancelled', 'refunded'], true)) {
                    $this->lifecycle->restockItems($order);
                }
                $order->delete();
                $deleted++;
            }
        });

        $message = $deleted === 1
            ? '1 order deleted successfully.'
            : "{$deleted} orders deleted successfully.";

        return redirect()
            ->route('admin.orders.index')
            ->with('success', $message);
    }
}
