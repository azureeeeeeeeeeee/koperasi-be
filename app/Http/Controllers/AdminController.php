<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/admin/user",
     *     tags={"Admin"},
     *     summary="Create User",
     *     description="Create a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fullname", "email"},
     *             @OA\Property(property="fullname", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *         )
     *     ),
     *     @OA\Response(response=201, description="User berhasil dibuat"),
     *     @OA\Response(response=400, description="Daftar sebagai pengguna harus menggunakan email ITK")
     * )
     */
    public function create_pengguna(Request $request) {
            $fields = $request->validate([
                'fullname' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'tipe' => 'required|in:pengguna,pegawai,penitip,admin',
                'status_keanggotaan' => 'in:aktif,tidak aktif,bukan anggota',
            ]);
            
    
            $fields['password'] = Hash::make('koperasi2025itk');
    
            $user = User::create($fields);
    
            $data = [
                'message' => 'User berhasil dibuat','data'=>$user
            ];
    
            return response()->json($data, 201);
        
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/user/{id}",
     *     tags={"Admin"},
     *     summary="Delete User",
     *     description="Delete a user by ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function delete_pengguna($id) {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        
        $user->delete();
        
        return response()->json(['message' => 'User berhasil dihapus'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/user",
     *     tags={"Admin"},
     *     summary="Get All Users",
     *     description="Retrieve list of all users",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Daftar user berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="fullname", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@student.itk.ac.id"),
     *                     @OA\Property(property="tipe", type="string", example="pengguna"),
     *                     @OA\Property(property="status_keanggotaan", type="string", example="aktif"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function get_pengguna()
    {
        $users = User::all();
        
        return response()->json([
            'data' => $users
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/user/{id}",
     *     tags={"Admin"},
     *     summary="Get User by ID",
     *     description="Retrieve specific user data",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data user berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="fullname", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@student.itk.ac.id"),
     *                 @OA\Property(property="tipe", type="string", example="pengguna"),
     *                 @OA\Property(property="status_keanggotaan", type="string", example="aktif"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="User tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function get_pengguna_by_id($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'data' => $user
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/user/{id}",
     *     tags={"Admin"},
     *     summary="Update User Data",
     *     description="Update user information",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="fullname", type="string", example="John Doe Updated"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@student.itk.ac.id"),
     *             @OA\Property(property="tipe", type="string", enum={"pengguna", "pegawai", "penitip", "admin"}, example="pengguna"),
     *             @OA\Property(property="status_keanggotaan", type="string", enum={"aktif", "tidak aktif", "suspended"}, example="active"),
     *             @OA\Property(property="nomor_hp", type="string", example="0800-0000-0000"),
     *             @OA\Property(property="saldo", type="number", format="float", example=50000),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Data user berhasil diperbarui"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update_pengguna(Request $request, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'tipe' => 'sometimes|in:penitip,pengguna,pegawai,admin',
            'status_keanggotaan' => 'sometimes|in:aktif,tidak aktif,bukan anggota',
            'nomor_hp' => 'sometimes|string|unique:users,nomor_hp,'.$id,
            'saldo' => 'sometimes|numeric',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Data user berhasil diperbarui',
        ], 200);
    }
}
