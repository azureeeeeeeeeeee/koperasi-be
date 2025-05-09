<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Type\Integer;



/**
 * @OA\Schema(
 *     schema="Cart",
 *     type="object",
 *     properties={
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="user_id", type="integer", example=1),
 *         @OA\Property(property="total_harga", type="number", format="float", example=20000),
 *         @OA\Property(property="sudah_bayar", type="boolean", example=false),
 *         @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-20T12:00:00Z"),
 *         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-04-20T12:00:00Z")
 *     }
 * )
 */
class CartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cart",
     *     summary="Get all carts (admin only)",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Cart"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index()
    {
        Log::info('Current user:', ['user' => Auth::user()]);
        Gate::authorize('viewAny', Cart::class);
        $carts = Cart::all();

        $data = [
            'message' => 'all carts fetched successfully',
            'data' => $carts
        ];

        return response()->json($data, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/cart/{id_user}/product/{id_product}",
     *     summary="Add item to cart",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"jumlah"},
     *             @OA\Property(property="jumlah", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product added to cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", ref="#/components/schemas/Cart")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found or stock not enough"),
     * )
 */
    public function add_item_to_cart(Request $request, int $id_user, int $id_product)
    {
        $cart = Cart::firstOrCreate(
            ['user_id' => $id_user, 'sudah_bayar' => false],
        );

        Gate::authorize('create', $cart);

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
            $potongan = $item->category->potongan ?? 0;
            $markup = $item->price * ($potongan / 100);
            $realPrice = $item->price + $markup;
            $total += $realPrice * $item->pivot->jumlah;
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart->load('products.category')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/cart/{id_user}",
     *     summary="Get active cart for user",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_id", type="integer"),
     *             @OA\Property(property="total_harga", type="integer"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="price", type="integer"),
     *                 @OA\Property(property="jumlah", type="integer"),
     *                 @OA\Property(property="subtotal", type="integer"),
     *             ))
     *         )
     *     )
     * )
     */
    public function show(Request $requesst, int $id_user)
    {
        $cart = Cart::firstOrCreate(
            ['user_id' => $id_user, 'sudah_bayar' => false],
        );

        Gate::authorize('view', $cart);

        $cart->load('products.category');

        $items = $cart->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name ?? null,
                'price' => $product->price,
                'jumlah' => $product->pivot->jumlah,
                'subtotal' => $product->price * $product->pivot->jumlah
            ];
        });

        return response()->json([
            'message' => 'Cart fetched successfully',
            'cart_id' => $cart->id,
            'total_harga' => $cart->total_harga,
            'items' => $items,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/cart/{id_user}/product/{id_product}",
     *     summary="Update quantity of a product in cart",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"jumlah"},
     *             @OA\Property(property="jumlah", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product quantity updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", ref="#/components/schemas/Cart")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id_user, int $id_product)
    {
        $cart = Cart::firstOrCreate(
            ['user_id' => $id_user, 'sudah_bayar' => false],
        );

        Gate::authorize('update', $cart);

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

        

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if ($existing) {
            $cart->products()->updateExistingPivot($id_product, [
                'jumlah' => $fields['jumlah']
            ]);
        } else {
            return response()->json([
                'message' => 'Produk tidak ada dalam keranjang'
            ]);
        }

        $total = 0;
        foreach ($cart->products as $item) {
            $potongan = $item->category->potongan ?? 0;
            $markup = $item->price * ($potongan / 100);
            $realPrice = $item->price + $markup;
            $total += $realPrice * $item->pivot->jumlah;
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart->load('products.category')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/cart/{id_user}/product/{id_product}",
     *     summary="Remove product from cart",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", ref="#/components/schemas/Cart")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function destroy(Request $request, int $id_user, int $id_product)
    {

        $cart = Cart::firstOrCreate(
            ['user_id' => $id_user, 'sudah_bayar' => false],
        );
        Gate::authorize('delete', $cart);
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
                $potongan = $item->category->potongan ?? 0;
                $markup = $item->price * ($potongan / 100);
                $realPrice = $item->price + $markup;
                $total += $realPrice * $item->pivot->jumlah;
            }
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Produk berhasil dihapus dari cart',
            'cart' => $cart->load('products.category')
        ]);
    }


    public function update_status_barang(Request $request, string $id_user ,int $id_cart)
    {
        // Validasi input status
        $fields = $request->validate([
            'status' => 'required|in:menunggu pegawai,akan dikirim,sudah dibooking,diterima pembeli',
        ]);

        // Cari cart berdasarkan id_cart dan id_user
        $cart = Cart::where('id', $id_cart)
                    ->where('user_id', $id_user)
                    ->first();

        // Jika cart tidak ditemukan, kembalikan response 404
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        // Update status barang
        $cart->status_barang = $fields['status'];
        $cart->save();

        // Kembalikan response sukses
        return response()->json([
            'message' => 'Status barang berhasil diperbarui',
            'cart' => $cart
        ], 200);
    }




    /**
     * @OA\Get(
     *     path="/api/cart/guest/{guest_id}",
     *     summary="Get cart for guest user",
     *     tags={"Cart"},
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="path",
     *         required=true,
     *         description="Guest user identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart_id", type="integer"),
     *             @OA\Property(property="total_harga", type="number", format="float"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="category", type="string"),
     *                     @OA\Property(property="price", type="number", format="float"),
     *                     @OA\Property(property="jumlah", type="integer"),
     *                     @OA\Property(property="subtotal", type="number", format="float")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function guest_show(Request $requesst, string $guest_id)
    {
        $cart = Cart::firstOrCreate(
            ['guest_id' => $guest_id, 'sudah_bayar' => false],
        );

        $cart->load('products.category');

        $items = $cart->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name ?? null,
                'price' => $product->price,
                'jumlah' => $product->pivot->jumlah,
                'subtotal' => $product->price * $product->pivot->jumlah
            ];
        });

        return response()->json([
            'message' => 'Cart fetched successfully',
            'cart_id' => $cart->id,
            'total_harga' => $cart->total_harga,
            'items' => $items,
        ]);
    }







    /**
     * @OA\Post(
     *     path="/api/cart/guest/{guest_id}/add/{id_product}",
     *     summary="Add product to guest cart",
     *     tags={"Cart"},
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="path",
     *         required=true,
     *         description="Guest user identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id_product",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"jumlah"},
     *             @OA\Property(property="jumlah", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product added to cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found / Stock not enough")
     * )
     */
    public function guest_add_item(Request $request, string $guest_id, int $id_product)
    {
        $cart = Cart::firstOrCreate(
            ['guest_id' => $guest_id, 'sudah_bayar' => false],
        );

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

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if ($existing) {
            $newQuantity = $existing->pivot->jumlah + $fields['jumlah'];
            $cart->products()->updateExistingPivot($id_product, [
                'jumlah' => $newQuantity
            ]);
        } else {
            $cart->products()->attach($id_product, [
                'jumlah' => $fields['jumlah']
            ]);
        }

        $total = 0;
        foreach ($cart->products as $item) {
            $potongan = $item->category->potongan ?? 0;
            $markup = $item->price * ($potongan / 100);
            $realPrice = $item->price + $markup;
            $total += $realPrice * $item->pivot->jumlah;
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart->load('products.category')
        ], 200);
    }



    /**
     * @OA\Put(
     *     path="/api/cart/guest/{guest_id}/update/{id_product}",
     *     summary="Update product quantity in guest cart",
     *     tags={"Cart"},
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="path",
     *         required=true,
     *         description="Guest user identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id_product",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"jumlah"},
     *             @OA\Property(property="jumlah", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product quantity updated in cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product or Cart not found"),
     *     @OA\Response(response=400, description="Not enough stock")
     * )
     */
    public function guest_update_item(Request $request, string $guest_id, int $id_product)
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
                'message' => 'Not enough stock'
            ], 400);
        }

        $cart = Cart::where('guest_id', $guest_id)
                    ->where('sudah_bayar', false)
                    ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found for this guest'
            ], 404);
        }

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if (!$existing) {
            return response()->json([
                'message' => 'Product not found in cart'
            ], 404);
        }

        $cart->products()->updateExistingPivot($id_product, [
            'jumlah' => $fields['jumlah']
        ]);

        $total = 0;
        foreach ($cart->products as $item) {
            $potongan = $item->category->potongan ?? 0;
            $markup = $item->price * ($potongan / 100);
            $realPrice = $item->price + $markup;
            $total += $realPrice * $item->pivot->jumlah;
        }

        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product quantity updated in cart',
            'cart' => $cart->load('products.category')
        ], 200);
    }






    /**
     * @OA\Delete(
     *     path="/api/cart/guest/{guest_id}/remove/{id_product}",
     *     summary="Remove product from guest cart",
     *     tags={"Cart"},
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="path",
     *         required=true,
     *         description="Guest user identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id_product",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="cart", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product or Cart not found")
     * )
     */
    public function guest_remove_item(Request $request, string $guest_id, int $id_product)
    {
        $product = Product::with(['category'])->find($id_product);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $cart = Cart::where('guest_id', $guest_id)
                    ->where('sudah_bayar', false)
                    ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found for this guest'
            ], 404);
        }

        $existing = $cart->products()->where('product_id', $id_product)->first();

        if (!$existing) {
            return response()->json([
                'message' => 'Product not found in cart'
            ], 404);
        }

        $cart->products()->detach($id_product);

        $total = 0;
        foreach ($cart->products as $item) {
            $potongan = $item->category->potongan ?? 0;
            $markup = $item->price * ($potongan / 100);
            $realPrice = $item->price + $markup;
            $total += $realPrice * $item->pivot->jumlah;
        }

        // Update the cart total price
        $cart->total_harga = $total;
        $cart->save();

        return response()->json([
            'message' => 'Product removed from cart',
            'cart' => $cart->load('products.category')
        ], 200);
    }

    
}
