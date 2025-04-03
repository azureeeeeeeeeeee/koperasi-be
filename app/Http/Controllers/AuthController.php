<?php

namespace App\Http\Controllers;
use App\Models\Otp;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticate a user and return a token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=404, description="Invalid username or password")
     * )
     */
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                "message" => "Username atau password salah!"
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

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentication"},
     *     summary="User registration",
     *     description="Register a new user",
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
     *     @OA\Response(response=201, description="User registered successfully"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */

    public function register_penitip(Request $request) {
        $fields = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8'
        ]);

        $user = User::create($fields);

        $user->tipe = 'penitip';
        $user->save();

        $data = [
            'message' => 'User berhasil register'
        ];

        return response()->json($data, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Authentication"},
     *     summary="User registration",
     *     description="Register a new user",
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
     *     @OA\Response(response=201, description="User registered successfully"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function register_pengguna(Request $request) {
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

        $otp = random_int(100000, 999999);
        
    
        // Store OTP in the database
        $otpCode = Otp::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes(6), // OTP valid for 5 minutes
        ]);

                  // Kirim email OTP
            Mail::to($user->email)->send(new OtpMail($otpCode));

            $data = [
        'message' => 'User berhasil register, OTP telah dikirim ke email Anda'
    ];
        return response()->json($data, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     description="Logs out the authenticated user",
     *     security={{ "bearerAuth": {} }},
     *     @OA\Response(response=200, description="Logout successful"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request) {
        $request->user()->tokens()->delete();

        $data = [
            "message" => "Logout berhasil"
        ];

        return response()->json($data, 200)->cookie(
            "TOKENID",
            null,
            -1
        );
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        // Generate a 6-digit OTP
        $otpCode = random_int(100000, 999999);
    
        // Store OTP in the database
        Otp::create([
            'user_id' => $user->id,
            'otp' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(6), // OTP valid for 5 minutes
        ]);
    
        // Send OTP via email or SMS (use Laravel Notifications or a mail service)
        // Example: Mail::to($user->email)->send(new OtpMail($otpCode));
    
        return response()->json(['message' => 'OTP sent successfully']);
    }
    
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        $otp = Otp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();        
    
        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }
    
        // OTP is valid, delete it
        $otp->delete();
    
        return response()->json(['message' => 'OTP verified successfully']);
    }
    
}
