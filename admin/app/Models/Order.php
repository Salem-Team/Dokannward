<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'order_number', 'user_id', 'customer_email', 'status', 'total_amount', 'subtotal', 'shipping_amount', 'tax_amount', 'shipping_address_id', 'billing_address_id', 'notes', 'payment_method', 'fulfillment_provider', 'delivery_tracking_number', 'placed_at', 'confirmed_at', 'shipped_at', 'delivered_at', 'canceled_at', 'canceled_by'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'canceled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    /** Display name from registered user or guest shipping address. */
    public function customerName(): string
    {
        if ($this->user?->name) {
            return $this->user->name;
        }

        return $this->shippingAddress?->recipient_name ?: 'Guest';
    }

    public function customerPhone(): ?string
    {
        return $this->shippingAddress?->phone;
    }

    public function customerEmail(): ?string
    {
        return $this->customer_email ?: $this->user?->email;
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->id)) {
                $order->id = (string) Str::uuid();
            }
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });

        // Keep the cached dashboard payload (revenue/counts/charts) from
        // ever showing an admin stale numbers right after they act on an
        // order — new orders, status changes, and deletions all bust it.
        static::saved(fn () => Cache::forget('admin.dashboard.payload'));
        static::deleted(fn () => Cache::forget('admin.dashboard.payload'));
    }

    /**
     * Hands out a guaranteed-unique, sequential, human-trackable order
     * number like "ZBR-000042". Every checkout path (Next.js API, legacy
     * Blade cart, admin-created orders) funnels through here so the store
     * only ever has one numbering scheme.
     *
     * The increment itself is a single atomic `UPDATE ... WHERE id = 1`,
     * which MySQL executes under a row lock — concurrent checkouts simply
     * queue up for that one row instead of racing, so two orders can never
     * be handed the same number.
     */
    public static function generateOrderNumber(string $prefix = 'ZBR'): string
    {
        return DB::transaction(function () use ($prefix) {
            DB::table('order_number_sequences')->where('id', 1)->increment('next_number');
            $sequence = (int) DB::table('order_number_sequences')->where('id', 1)->value('next_number') - 1;

            $number = sprintf('%s-%06d', $prefix, $sequence);

            // Defensive: extremely unlikely with the atomic counter above,
            // but if a legacy/manual row ever collides, skip ahead rather
            // than let a duplicate order number reach the unique constraint.
            while (static::where('order_number', $number)->exists()) {
                DB::table('order_number_sequences')->where('id', 1)->increment('next_number');
                $sequence = (int) DB::table('order_number_sequences')->where('id', 1)->value('next_number') - 1;
                $number = sprintf('%s-%06d', $prefix, $sequence);
            }

            return $number;
        });
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class, 'order_id')->latest();
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderByDesc('created_at');
    }

    /** How much of the order total is still refundable. */
    public function refundableRemaining(): float
    {
        $refunded = (float) $this->returns()->sum('refund_amount');

        return max(0, round((float) $this->total_amount - $refunded, 2));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canceledBy()
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }
}
