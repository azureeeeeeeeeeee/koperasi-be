<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Product",
 *     required={"name", "price", "stock"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Produk A"),
 *     @OA\Property(property="price", type="number", format="float", example=10000),
 *     @OA\Property(property="stock", type="integer", example=15),
 *     @OA\Property(property="category_id", type="integer", example=2),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 */
class ProductController extends Controller
{
    
    /**
     * GET /api/product
     * 
     * @OA\Get(
     *     path="/api/product",
     *     summary="Ambil semua produk",
     *     tags={"Product"},
     *     @OA\Response(
     *         response=200,
     *         description="Daftar produk berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar produk berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tidak ada produk")
     * )
     */
    public function show_all_product()
    {
        try {
            $products = Product::with(['category', 'user'])->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada produk yang tersedia.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Daftar produk berhasil diambil.',
                'data' => $products,
            ], 200);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/product/{id}
     * 
     * @OA\Get(
     *     path="/api/product/{id}",
     *     summary="Ambil detail produk berdasarkan ID",
     *     tags={"Product"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail produk berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail produk berhasil diambil."),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Produk tidak ditemukan")
     * )
     */
    public function get_product_data($id)
    {
        try {
            $product = Product::with(['category', 'user'])->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail produk berhasil diambil.',
                'data' => $product,
            ], 200);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/product
     * 
     * @OA\Post(
     *     path="/api/product",
     *     summary="Buat produk baru",
     *     tags={"Product"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","price","stock","category"},
     *             @OA\Property(property="name", type="string", example="Produk A"),
     *             @OA\Property(property="price", type="number", format="float", example=10000),
     *             @OA\Property(property="stock", type="integer", example=15),
     *             @OA\Property(property="category", type="string", example="Elektronik")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Produk telah berhasil dibuat",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produk telah berhasil dibuat."),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     )
     * )
     */
    public function create_product(Request $request)
    {
        try {
            Gate::authorize('create', Product::class);
            $fields = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'category' => 'required|string|max:30'
            ]);

            $fields['user_id'] = $request->user()->id;
            $fields['category_id'] = Category::where('name', $fields['category'])->firstOrFail()->id;

            // unset($fields['category']);

            $product = Product::create($fields);

            return response()->json([
                'success' => true,
                'message' => 'Produk telah berhasil dibuat.',
                'data' => $product,
            ], 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /api/product/{id}
     * 
     * @OA\Put(
     *     path="/api/product/{id}",
     *     summary="Update data produk berdasarkan ID",
     *     tags={"Product"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","price","stock","category"},
     *             @OA\Property(property="name", type="string", example="Produk A"),
     *             @OA\Property(property="price", type="number", example=10000),
     *             @OA\Property(property="stock", type="integer", example=15),
     *             @OA\Property(property="category", type="string", example="Elektronik")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data produk telah berhasil diupdate",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data produk telah berhasil diupdate."),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Produk tidak ditemukan")
     * )
     */
    public function update_product_data(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            Gate::authorize('update', $product);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan.',
                ], 404);
            }

            $fields = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'category' => 'required|string|min:0',
            ]);

            $fields['user_id'] = $request->user()->id;
            $fields['category_id'] = Category::where('name', $fields['category'])->firstOrFail()->id;

            $product->update($fields);

            return response()->json([
                'success' => true,
                'message' => 'Data produk telah berhasil diupdate.',
                'data' => $product,
            ], 200);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/product/{id}
     * 
     * @OA\Delete(
     *     path="/api/product/{id}",
     *     summary="Hapus produk berdasarkan ID",
     *     tags={"Product"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Produk berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Produk telah berhasil dihapus.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Produk tidak ditemukan")
     * )
     */
    public function remove_product($id)
    {
        try {
            $product = Product::find($id);

            Gate::authorize('delete', $product);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan.',
                ], 404);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produk telah berhasil dihapus.',
            ], 200);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle exceptions and return a JSON response.
     *
     * @param \Exception $e
     * @return \Illuminate\Http\JsonResponse
     */

    private function handleException(\Exception $e)
    {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null,
        ], 500);
    }

}
