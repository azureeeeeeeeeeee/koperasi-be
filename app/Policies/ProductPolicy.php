<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->tipe == 'admin') {
            return true;
        }
        elseif ($user->tipe == 'pengguna') {
            $user->status_keanggotaan == 'aktif' ? true : false;
        }
        return $user->tipe == 'penitip' ? true : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->tipe == 'admin') {
            return true;
        }

        return $user->id == $product->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->tipe == 'admin') {
            return true;
        }

        return $user->id == $product->user_id;
    }
}
