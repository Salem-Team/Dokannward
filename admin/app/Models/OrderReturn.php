<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'order_id',
        'return_number',
        'status',
        'reason',
        'notes',
        'refund_amount',
        'suggested_amount',
        'restock',
        'processed_by',
        'refunded_at',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'suggested_amount' => 'decimal:2',
        'restock' => 'boolean',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderReturn $return) {
            if (empty($return->id)) {
                $return->id = (string) Str::uuid();
            }
            if (empty($return->return_number)) {
                $return->return_number = static::generateReturnNumber();
            }
        });

        static::saved(fn () => Cache::forget('admin.dashboard.payload'));
        static::deleted(fn () => Cache::forget('admin.dashboard.payload'));
    }

    /** Sequential credit-note numbers like DW-R-000042 (prefix from branding). */
    public static function generateReturnNumber(?string $prefix = null): string
    {
        $prefix = $prefix ?? (Order::resolveNumberPrefix().'-R');

        return DB::transaction(function () use ($prefix) {
            DB::table('order_number_sequences')->where('id', 2)->increment('next_number');
            $sequence = (int) DB::table('order_number_sequences')->where('id', 2)->value('next_number') - 1;

            if ($sequence < 1) {
                DB::table('order_number_sequences')->updateOrInsert(
                    ['id' => 2],
                    ['next_number' => 2, 'created_at' => now(), 'updated_at' => now()]
                );
                $sequence = 1;
            }

            $number = sprintf('%s-%06d', $prefix, $sequence);

            while (static::where('return_number', $number)->exists()) {
                DB::table('order_number_sequences')->where('id', 2)->increment('next_number');
                $sequence = (int) DB::table('order_number_sequences')->where('id', 2)->value('next_number') - 1;
                $number = sprintf('%s-%06d', $prefix, $sequence);
            }

            return $number;
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderReturnItem::class, 'order_return_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
