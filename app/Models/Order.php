<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
