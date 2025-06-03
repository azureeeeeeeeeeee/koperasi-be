<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

class CartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tipe == 'admin' || $user->tipe == 'pegawai';
    }

    public function create(User $user, Cart $cart): bool
    {
        return $user->id == $cart->user_id;
    }

    public function view(User $user, Cart $cart): bool
    {
        return $user->tipe == 'admin' || $cart->user_id == $user->id;
    }

    
    public function update(User $user, Cart $cart): bool
    {
        return $user->tipe == 'admin' || $cart->user_id == $user->id;
    }

    public function delete(User $user, Cart $cart): bool
    {
        return $user->tipe == 'admin' || $cart->user_id == $user->id;
    }
}
