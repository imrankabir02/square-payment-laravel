<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    const ALLOWED_CURRENCIES = ['USD'];
    const ALLOWED_STATUSES = ['pending', 'completed', 'failed'];

    protected $fillable = [
        'order_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'meta'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
