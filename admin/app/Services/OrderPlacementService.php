<?php

namespace App\Services;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared place-order pipeline for storefront checkout and admin-created orders.
 * Always re-prices from the database and decrements stock under row locks.
 */
class OrderPlacementService
{
    /**
     * @param  array{
     *   recipient_name: string,
     *   phone: string,
     *   email?: ?string,
     *   line_1: string,
     *   line_2?: ?string,
     *   city: string,
     *   postal_code?: ?string,
     *   country?: ?string,
     *   latitude?: float|string|null,
     *   longitude?: float|string|null,
     *   place_name?: ?string,
     *   location_source?: ?string,
     *   notes?: ?string,
     *   status?: string,
     *   user_id?: ?string,
     *   items: list<array{product_id: string, variant_id?: ?string, name: string, qty: int, sku?: ?string}>
     * }  $data
     */
    public function place(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            if (empty($data['user_id'])) {
                $customer = app(GuestCustomerService::class)->findOrCreate($data);
                $data['user_id'] = $customer->id;
            }

            $addressId = (string) Str::uuid();
            $latitude = $this->coordinate($data['latitude'] ?? null, -90, 90);
            $longitude = $this->coordinate($data['longitude'] ?? null, -180, 180);
            $source = $data['location_source'] ?? null;
            if (! in_array($source, ['map', 'gps', 'search', 'typed'], true)) {
                $source = ($latitude !== null && $longitude !== null) ? 'map' : 'typed';
            }

            DB::table('addresses')->insert([
                'id' => $addressId,
                'user_id' => $data['user_id'] ?? null,
                'label' => 'Shipping',
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'line_1' => $data['line_1'],
                'line_2' => $data['line_2'] ?? null,
                'city' => $data['city'],
                'state' => null,
                'postal_code' => $data['postal_code'] ?? '',
                'country' => $data['country'] ?? 'Egypt',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'place_name' => isset($data['place_name']) ? (trim((string) $data['place_name']) ?: null) : null,
                'location_source' => $source,
                'is_default_shipping' => false,
                'is_default_billing' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $resolved = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $index => $item) {
                $line = $this->resolveLine($item, $index);
                $resolved[] = $line;
                $subtotal += $line['line_total'];
            }

            [$shippingAmount, $taxAmount] = $this->computeShippingAndTax($subtotal);
            $total = round($subtotal + $shippingAmount + $taxAmount, 2);
            $status = $data['status'] ?? 'pending';
            $payments = self::paymentOptions();
            $paymentMethod = $data['payment_method'] ?? null;

            if (! in_array($paymentMethod, array_column($payments['methods'], 'key'), true)) {
                $paymentMethod = $payments['default_method'];
            }

            $order = Order::create([
                'id' => (string) Str::uuid(),
                'user_id' => $data['user_id'] ?? null,
                'status' => $status,
                'customer_email' => $data['email'] ?? null,
                'total_amount' => $total,
                'subtotal' => round($subtotal, 2),
                'shipping_amount' => $shippingAmount,
                'tax_amount' => $taxAmount,
                'shipping_address_id' => $addressId,
                'billing_address_id' => $addressId,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $paymentMethod,
                'placed_at' => now(),
                'confirmed_at' => in_array($status, ['processing', 'shipped', 'delivered'], true) ? now() : null,
            ]);

            $rows = array_map(fn ($line) => [
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'sku' => $line['sku'],
                'name' => $line['name'],
                'unit_price' => $line['unit_price'],
                'qty' => $line['qty'],
                'line_total' => $line['line_total'],
                'tax' => 0,
                'created_at' => now(),
            ], $resolved);

            OrderItem::insert($rows);

            return $order->fresh(['shippingAddress', 'items', 'user']);
        });

        NotificationService::newOrder($order);
        StorefrontRevalidator::purge(['/', '/collections', '/collections/all', '/search']);

        return $order;
    }

    /**
     * @param  array{product_id: string, variant_id?: ?string, name: string, qty: int, sku?: ?string}  $item
     * @return array{product_id: string, variant_id: ?string, sku: string, name: string, unit_price: float, qty: int, line_total: float}
     */
    public function resolveLine(array $item, int $index): array
    {
        $product = Product::whereKey($item['product_id'])->lockForUpdate()->first();

        if (! $product || $product->status !== 'active') {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => 'This product is no longer available.',
            ]);
        }

        $qty = (int) $item['qty'];
        $variantId = $item['variant_id'] ?? null;
        $displayName = trim((string) ($item['name'] ?? '')) ?: (string) $product->translated_name;

        if ($variantId) {
            $variant = ProductVariant::whereKey($variantId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $variant || ! $variant->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.variant_id" => 'This color / variant is no longer available.',
                ]);
            }

            return $this->resolveVariantLine($variant, $product, $item, $index, $qty, $displayName);
        }

        $hasColorVariants = $product->variants()
            ->where('is_active', true)
            ->whereNotNull('color_id')
            ->exists();

        if ($hasColorVariants) {
            throw ValidationException::withMessages([
                "items.{$index}.variant_id" => 'Please choose a color for this product.',
            ]);
        }

        $colorlessVariant = $product->variants()
            ->where('is_active', true)
            ->whereNull('color_id')
            ->orderByDesc('is_default')
            ->lockForUpdate()
            ->first();

        if ($colorlessVariant) {
            return $this->resolveVariantLine($colorlessVariant, $product, $item, $index, $qty, $displayName);
        }

        if (! $product->in_stock) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => 'This product is sold out.',
            ]);
        }

        $unitPrice = (float) $product->price;

        return [
            'product_id' => $product->id,
            'variant_id' => null,
            'sku' => $item['sku'] ?? $product->sku,
            'name' => $displayName,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
        ];
    }

    /**
     * @param  array{sku?: ?string}  $item
     * @return array{product_id: string, variant_id: string, sku: string, name: string, unit_price: float, qty: int, line_total: float}
     */
    private function resolveVariantLine(
        ProductVariant $variant,
        Product $product,
        array $item,
        int $index,
        int $qty,
        string $displayName,
    ): array {
        if ($variant->stock < $qty) {
            throw ValidationException::withMessages([
                "items.{$index}.qty" => $variant->stock <= 0
                    ? 'This item is sold out.'
                    : "Only {$variant->stock} left in stock.",
            ]);
        }

        $variant->decrement('stock', $qty);
        $product->syncAvailabilityFromVariants();

        $unitPrice = (float) $variant->price;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku ?: ($item['sku'] ?? $product->sku),
            'name' => $displayName,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
        ];
    }

    /**
     * Shipping, tax and payment config the store bills against — the same cached
     * payload the storefront reads from /api/settings/checkout.
     *
     * @return array<string, mixed>
     */
    private static function checkoutConfig(): array
    {
        return Cache::remember(
            SiteSettingController::CHECKOUT_CACHE_KEY,
            now()->addMinutes(30),
            fn () => SettingsController::checkoutPayload()
        );
    }

    /**
     * @return array{
     *     methods: list<array{key: string, label: string, instructions: string}>,
     *     default_method: string
     * }
     */
    public static function paymentOptions(): array
    {
        $payload = self::checkoutConfig();

        return [
            'methods' => $payload['payment_methods'] ?? [],
            'default_method' => $payload['default_payment_method'] ?? 'cash',
        ];
    }

    /** Shopper-facing label for a stored payment method key. */
    public static function paymentLabel(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        foreach (self::paymentOptions()['methods'] as $method) {
            if (($method['key'] ?? null) === $key) {
                return $method['label'];
            }
        }

        return SettingsController::DEFAULTS['payments'][$key.'_label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @return array{0: float, 1: float} [shipping, tax]
     */
    public function computeShippingAndTax(float $subtotal): array
    {
        $payload = self::checkoutConfig();

        $fee = (float) ($payload['standard_shipping_fee'] ?? 0);
        $shippingAmount = max(0, $fee);

        $taxAmount = 0.0;
        if (! empty($payload['tax_enabled'])) {
            $rate = (float) ($payload['tax_rate'] ?? 0);
            $taxAmount = round($subtotal * ($rate / 100), 2);
        }

        return [round($shippingAmount, 2), $taxAmount];
    }

    private function coordinate(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return null;
        }

        return round($number, 7);
    }
}
