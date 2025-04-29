<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'guest_id', 'total_harga', 'sudah_bayar'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'cart_item' ,'cart_id', 'product_id')
                    ->withPivot('jumlah')
                    ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
