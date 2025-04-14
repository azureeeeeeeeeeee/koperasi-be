<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;



/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Category",
 *     required={"name", "potongan"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Makanan"),
 *     @OA\Property(property="potongan", type="number", format="float", example=10.00),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 */
class CategoryController extends Controller
{
    /**
     * POST /api/category
     *
     * @OA\Post(
     *     path="/api/category",
     *     summary="Buat kategori baru",
     *     tags={"Category"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "potongan"},
     *             @OA\Property(property="name", type="string", example="Makanan"),
     *             @OA\Property(property="potongan", type="number", format="float", example=10.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Kategori berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="kategori berhasil ditambahkan")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Tidak diizinkan")
     * )
     */
    public function create_category(Request $request) {
        Gate::authorize('create', Category::class);
        $fields = $request->validate([
            'name' => 'required|string|max:30',
            'potongan' => 'required|numeric|min:0',
        ]);

        $category = Category::create($fields);
        
        return response()->json([
            'message' => 'kategori berhasil ditambahkan'
        ]);
    }
    

    /**
     * GET /api/category
     *
     * @OA\Get(
     *     path="/api/category",
     *     summary="Ambil semua kategori",
     *     tags={"Category"},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil ambil semua kategori",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="kategori berhasil diambil dari database"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Category"))
     *         )
     *     )
     * )
     */
    public function get_all_categories(Request $request) {
        $categories = Category::all();

        return response()->json([
            'message' => 'kategori berhasil diambil dari database',
            'data' => $categories
        ]);
    }




    /**
     * GET /api/category/{id}
     *
     * @OA\Get(
     *     path="/api/category/{id}",
     *     summary="Ambil satu kategori berdasarkan ID",
     *     tags={"Category"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kategori",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Kategori ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="kategori berhasil diambil dari database"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Kategori tidak ditemukan"),
     *     @OA\Response(response=403, description="Tidak diizinkan")
     * )
     */
    public function get_one_category(Request $request, $id) {
        Gate::authorize('view', Category::class);
        $category = Category::where('id', $id)->firstOrFail();

        return response()->json([
            'message' => 'kategori berhasil diambil dari database',
            'data' => $category
        ]);
    }

    /**
     * PUT /api/category/{id}
     *
     * @OA\Put(
     *     path="/api/category/{id}",
     *     summary="Update potongan dari kategori berdasarkan ID",
     *     tags={"Category"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kategori",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"potongan"},
     *             @OA\Property(property="potongan", type="number", format="float", example=15.50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil update kategori",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="berhasil edit kategori"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Kategori tidak ditemukan"),
     *     @OA\Response(response=403, description="Tidak diizinkan")
     * )
     */
    public function update_category(Request $request, $id) {
        Gate::authorize('update', Category::class);
        $category = Category::where('id', $id)->firstOrFail();
        
        $fields = $request->validate([
            'potongan' => 'required|numeric|min:0',
        ]);
        
        $category->potongan = $fields['potongan'];
        $category->save();
        
        return response()->json([
            'message' => 'berhasil edit kategori',
            'data' => $category
        ]);
    }
    



    /**
     * DELETE /api/category/{id}
     *
     * @OA\Delete(
     *     path="/api/category/{id}",
     *     summary="Hapus kategori berdasarkan ID",
     *     tags={"Category"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kategori",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Kategori berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="kategori berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Kategori tidak ditemukan"),
     *     @OA\Response(response=403, description="Tidak diizinkan")
     * )
 */
    public function delete_category(Request $request, $id) {
        Gate::authorize('delete', Category::class);
        $category = Category::where('id', $id)->firstOrFail();

        $category->delete();

        return response()->json([
            'message' => 'kategori berhasil dihapus'
        ]);
    }
}
