<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\User;
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
        Log::info('Current user for cart listing:', ['user_id' => Auth::id()]);

        // Otorisasi apakah pengguna dapat melihat daftar keranjang
        Gate::authorize('viewAny', Cart::class);

        // Ambil semua keranjang dan eager load relasi 'products' beserta 'category' dari produk
        // Ini membantu menghindari masalah N+1 query.
        // Anda mungkin ingin memfilter keranjang berdasarkan pengguna tertentu jika diperlukan,
        // misalnya: $carts = Cart::where('user_id', Auth::id())->with('products.category')->get();
        // Atau jika ini untuk admin yang melihat semua keranjang:
        $carts = Cart::with('products.category', 'user')->get();

        // Transformasi koleksi keranjang untuk menyertakan detail item yang diformat
        $formattedCarts = $carts->map(function ($cart) {
            // Untuk setiap keranjang, petakan produk-produknya ke format yang diinginkan
            $items = $cart->products->map(function ($product) {
                // Pastikan relasi pivot ada dan memiliki 'jumlah'
                $jumlah = $product->pivot ? $product->pivot->jumlah : 0;
                $subtotal = $product->price * $jumlah;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category ?? null, // Ambil nama kategori jika ada
                    'price' => $product->price,
                    'jumlah' => $jumlah, // Kuantitas dari tabel pivot
                    'stock' => $product->stock,
                    'subtotal' => $subtotal,
                ];
            });

            // Kembalikan struktur yang diinginkan untuk setiap keranjang individual
            return [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'user' => $cart->user ? [
                    'id' => $cart->user->id,
                    'fullname' => $cart->user->fullname,
                ] : null,
                'guest_id' => $cart->guest_id, // Tambahkan jika ada dan relevan
                'total_harga' => $cart->total_harga,
                'status_barang' => $cart->status_barang, // Tambahkan jika ada dan relevan
                'sudah_bayar' => $cart->sudah_bayar,
                'created_at' => $cart->created_at->toDateTimeString(), // Format tanggal jika perlu
                'updated_at' => $cart->updated_at->toDateTimeString(), // Format tanggal jika perlu
                'items' => $items, // Daftar produk yang telah diformat dalam keranjang ini
            ];
        });

        $responseData = [
            'message' => 'All carts fetched successfully',
            'data' => $formattedCarts, // Kirim koleksi keranjang yang telah ditransformasi
        ];

        return response()->json($responseData, 200);
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
            ['user_id' => $id_user, 'sudah_bayar' => 0],
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
            ['user_id' => $id_user, 'sudah_bayar' => 0],
        );

        Gate::authorize('view', $cart);

        
        $cart->load('products.category');

        $items = $cart->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category ?? null,
                'price' => $product->price,
                'jumlah' => $product->pivot->jumlah,
                'subtotal' => ($product->price * (1 + $product->category->potongan)) * $product->pivot->jumlah
            ];
        });

        return response()->json([
            'message' => 'Cart fetched successfully',
            'cart_id' => $cart->id,
            'total_harga' => $cart->total_harga,
            'status_barang' => $cart->status_barang,
            'sudah_bayar' => $cart->sudah_bayar,
            'items' => $items,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/cart/{id_user}/history",
     *     summary="Get all carts for a user",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_user",
     *         in="path",
     *         required=true,
     *         description="User ID whose carts will be fetched",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Carts for user fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart for user fetched successfully"),
     *             @OA\Property(
     *                 property="carts",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="cart_id", type="integer", example=1),
     *                     @OA\Property(property="total_harga", type="integer", example=50000),
     *                     @OA\Property(property="status_barang", type="string", example="menunggu"),
     *                     @OA\Property(property="sudah_bayar", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=101),
     *                             @OA\Property(property="name", type="string", example="Produk A"),
     *                             @OA\Property(property="category", type="string", example="Elektronik"),
     *                             @OA\Property(property="price", type="integer", example=20000),
     *                             @OA\Property(property="jumlah", type="integer", example=2),
     *                             @OA\Property(property="subtotal", type="integer", example=42000)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access to user's carts"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User or carts not found"
     *     )
     * )
     */
    public function showByUser(Request $requesst, int $id_user)
    {
        $carts = Cart::where(
            ['user_id' => $id_user, 'sudah_bayar' => 1],
        )->get();

        foreach ($carts as $cart) {
            Gate::authorize('view', $cart);
            $cart->load('products.category');
        }

        

        $finalCarts = $carts->map(function ($cart) {
            $items = $cart->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name ?? null,
                    'price' => $product->price,
                    'jumlah' => $product->pivot->jumlah,
                    'subtotal' => ($product->price * (1 + ($product->category->potongan / 100))) * $product->pivot->jumlah
                ];
            });
    
            return [
                'cart_id' => $cart->id,
                'total_harga' => $cart->total_harga,
                'status_barang' => $cart->status_barang,
                'sudah_bayar' => $cart->sudah_bayar,
                'items' => $items,
            ];
        });

        return response()->json([
            'message' => 'Cart for user fetched successfully',
            'carts' => $finalCarts,
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
            ['user_id' => $id_user, 'sudah_bayar' => 0],
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
            ['user_id' => $id_user, 'sudah_bayar' => 0],
        );
        Gate::authorize('delete', $cart);
        $product = Product::with(['category'])->find($id_product);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $cart = Cart::where('user_id', $id_user)
                ->where('sudah_bayar', 0)
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

    /**
     * @OA\Put(
     *     path="/api/cart/{id_user}/update-status",
     *     summary="Update the status of a cart",
     *     tags={"Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id_user",
     *         in="path",
     *         required=true,
     *         description="ID of the user whose cart status will be updated",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 description="The new status of the cart",
     *                 enum={"menunggu pegawai", "akan dikirim", "sudah dibooking", "diterima pembeli"},
     *                 example="akan dikirim"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Status barang berhasil diperbarui"),
     *             @OA\Property(property="cart", ref="#/components/schemas/Cart")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cart has not been paid yet",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart has not been paid yet")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User or cart not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not found or Cart not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */

    public function update_status_barang(Request $request, string $id_user)
    {
        $fields = $request->validate([
            'status' => 'required|in:menunggu pegawai,akan dikirim,sedang dikirim,sudah dibooking,diterima pembeli',
        ]);

        $userExists = User::where('id', $id_user)->exists();
        if (!$userExists) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $cart = Cart::where('user_id', $id_user)
                    ->where('sudah_bayar', 0)
                    ->first();

        if (!$cart) {
            $unpaidCart = Cart::where('user_id', $id_user)
                            ->where('sudah_bayar', 0)
                            ->exists();

            if ($unpaidCart) {
                return response()->json([
                    'message' => 'Cart has not been paid yet'
                ], 400);
            }

            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $cart->status_barang = $fields['status'];
        $cart->save();

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
            ['guest_id' => $guest_id, 'sudah_bayar' => 0],
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
            'status_barang' => $cart->status_barang,
            'sudah_bayar' => $cart->sudah_bayar,
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
            ['guest_id' => $guest_id, 'sudah_bayar' => 0],
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
                    ->where('sudah_bayar', 0)
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
                    ->where('sudah_bayar', 0)
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

    /**
     * @OA\Put(
     *     path="/api/cart/guest/{guest_id}/update-status",
     *     summary="Update the status of a guest cart",
     *     tags={"Cart"},
     *     @OA\Parameter(
     *         name="guest_id",
     *         in="path",
     *         required=true,
     *         description="Guest user identifier",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 description="The new status of the cart",
     *                 enum={"menunggu pegawai", "akan dikirim", "sudah dibooking", "diterima pembeli"},
     *                 example="akan dikirim"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Status barang berhasil diperbarui"),
     *             @OA\Property(property="cart", ref="#/components/schemas/Cart")
     *         )
     *     ),
     * 
     *      @OA\Response(
     *         response=400,
     *         description="Cart has not been paid yet",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart has not been paid yet")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function guest_update_status(Request $request, string $guest_id)
    {
        $fields = $request->validate([
            'status' => 'required|in:menunggu pegawai,akan dikirim,sudah dibooking,diterima pembeli',
        ]);

        $cart = Cart::where('guest_id', $guest_id)
                    ->where('sudah_bayar', 1)
                    ->first();

        if (!$cart) {
            $unpaidCart = Cart::where('guest_id', $guest_id)
                          ->where('sudah_bayar', 0)
                          ->exists();
            if ($unpaidCart) {
            return response()->json([
                'message' => 'Cart has not been paid yet'
            ], 400);
            }
            
            return response()->json([
                'message' => 'Cart not found'
            ], 404);
        }

        $cart->status_barang = $fields['status'];
        $cart->save();

        return response()->json([
            'message' => 'Status barang berhasil diperbarui',
            'cart' => $cart
        ], 200);
    }
    
}
