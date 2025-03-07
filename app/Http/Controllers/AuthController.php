<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                "message" => "Username atau password salah !"
            ], 404);
        }

        $token = $user->createToken($user->email)->plainTextToken;
        $data = [
            "message" => "Login Berhasil"
        ];

        return response()->json($data, 200)->cookie(
            'TOKENID',
            $token,
            1440,
            null,
            false,
            false
        );
    }

    public function register(Request $request) {
        $fields = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
            'tipe' => 'nullable|in:pengguna,pegawai,penitip,admin',
            'status_keanggotaan' => 'nullable|in:aktif,tidak aktif,bukan anggota',
        ]);


        $user = User::create($fields);
        $token = $user->createToken($request->email)->plainTextToken;

        $data = [
            'message' => 'User berhasil register'
        ];

        return response()->json($data, 201)->cookie(
            'TOKENID',
            $token,
            1440,
            null,
            false,
            false
        );
    }

    public function logout(Request $request) {
        $request->user()->tokens()->delete();

        $data = [
            "message" => "Logout berhasil"
        ];

        return response()->json($data, 200);
    }
}
