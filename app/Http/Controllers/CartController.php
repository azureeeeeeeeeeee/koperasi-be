<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Ramsey\Uuid\Type\Integer;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $carts = Cart::all();

        $data = [
            'message' => 'all carts fetched successfully',
            'data' => $carts
        ];

        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function add_item_to_cart(Request $request, int $id_user, int $id_product)
    {
        $fields = $request->validate([
            'jumlah' => 'required|integer|min:1',
        ]);

        $product = Product::with(['category'])->find($id_product);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        if ($product->stock - $fields['jumlah'] < 0) {
            return response()->json([
                'message' => 'Stok produk tidak cukup'
            ], 404);
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $id_user, 'sudah_bayar' => false],
        );

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if ($existing) {
            $newJumlah = $existing->pivot->jumlah + $fields['jumlah'];
            $cart->products()->updateExistingPivot($id_product, [
                'jumlah' => $newJumlah
            ]);
        } else {
            $cart->products()->attach($id_product, [
                'jumlah' => $fields['jumlah']
            ]);
        }

        $total = 0;
        foreach ($cart->products as $item) {
            $total += $item->price * $item->pivot->jumlah;
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart->load('products.category')
        ]);


    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id_user, int $id_product)
    {
        $product = Product::with(['category'])->find($id_product);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $cart = Cart::where('user_id', $id_user)
                ->where('sudah_bayar', false)
                ->firstOrFail();

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if (!$existing) {
            return response()->json([
                'message' => 'Produk tidak ditemukan dalam cart ini'
            ], 404);
        }
        $cart->products()->detach($id_product);
        $cart->load('products');

        $total = 0;
        foreach ($cart->products as $item) {
            if ($item->id != $id_product) {
                $total += $item->price * $item->pivot->jumlah;
            }
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Produk berhasil dihapus dari cart',
            'cart' => $cart->load('products.category')
        ]);
    }
}
