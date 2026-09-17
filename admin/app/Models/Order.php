<?php

namespace App\Models;

use App\Support\Branding;
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
     * number like "DW-000042". Prefix comes from Branding::exportPrefix()
     * (ROOTK / platform_brands) — never a hardcoded product code.
     *
     * The increment itself is a single atomic `UPDATE ... WHERE id = 1`,
     * which MySQL executes under a row lock — concurrent checkouts simply
     * queue up for that one row instead of racing, so two orders can never
     * be handed the same number.
     */
    public static function generateOrderNumber(?string $prefix = null): string
    {
        $prefix = $prefix ?? static::resolveNumberPrefix();

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

    /** Short alphanumeric code derived from the tenant export prefix. */
    public static function resolveNumberPrefix(): string
    {
        try {
            $raw = (string) Branding::exportPrefix();
        } catch (\Throwable) {
            $raw = 'ORD';
        }

        return static::normalizeNumberPrefix($raw);
    }

    public static function normalizeNumberPrefix(string $raw): string
    {
        $raw = trim($raw);
        $alnum = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $raw));
        if (strlen($alnum) >= 2 && strlen($alnum) <= 5) {
            return $alnum;
        }

        $parts = preg_split('/[\s\-_]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            $acro = '';
            foreach (array_slice($parts, 0, 3) as $part) {
                $letter = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $part), 0, 1));
                if ($letter !== '') {
                    $acro .= $letter;
                }
            }

            return $acro !== '' ? $acro : 'ORD';
        }

        return strtoupper(substr($alnum !== '' ? $alnum : 'ORD', 0, 3));
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
