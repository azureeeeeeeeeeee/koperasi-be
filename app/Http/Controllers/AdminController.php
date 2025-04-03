<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Http\Controllers\Controller;
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
     *             required={"fullname", "email", "password", "password_confirmation"},
     *             @OA\Property(property="fullname", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
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
                'password' => 'required|confirmed|min:8',
            ]);
            
    
            $emailParts = explode('@', $fields['email']);
        
            if (isset($emailParts[1]) && strpos($emailParts[1], '.itk.ac.id') === false) {
                return response()->json(['message' => 'Daftar sebagai pengguna harus menggunakan email ITK'], 400);
            }
    
            $user = User::create($fields);
    
            $data = [
                'message' => 'User berhasil dibuat'
            ];
    
            return response()->json($data, 201);
        
    }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/admin/user",
    //  *     tags={"Admin"},
    //  *     summary="Delete User",
    //  *     description="Delete a user",
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"fullname", "email", "password", "password_confirmation"},
    //  *             @OA\Property(property="fullname", type="string", example="John Doe"),
    //  *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
    //  *             @OA\Property(property="password", type="string", format="password", example="password123"),
    //  *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=201, description="User registered successfully"),
    //  * )
    //  */
    // public function delete_pengguna(Request $request) {

    //     $data = [
    //         'message' => 'User berhasil dihapus'
    //     ];

    //     return response()->json($data, 201);
    // }
}
