<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'order_status_history';

    public $timestamps = false; // created_at stored directly

    protected $fillable = ['id', 'order_id', 'status', 'note', 'changed_by', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
