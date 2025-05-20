<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'order_id',
        'user_id',
        'cart_id',
        'payment_method',
        'payment_status',
        'payment_date',
        'amount',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}
