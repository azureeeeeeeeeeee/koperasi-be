<?php

namespace App\Http\Controllers;
use App\Models\Otp;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\otpMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        // Check if user is verified
        // if (!$user->is_verified) {
        //     // Check if there's a valid OTP
        //     $existingOtp = Otp::where('user_id', $user->id)
        //         ->where('expires_at', '>', Carbon::now())
        //         ->first();

        //     if (!$existingOtp) {
        //         // Generate new verification token if no valid OTP exists
        //         $verificationToken = Str::uuid();
                
        //         Otp::create([
        //             'user_id' => $user->id,
        //             'otp' => $verificationToken,
        //             'expires_at' => Carbon::now()->addHours(24),
        //         ]);
                
        //         try {
        //             // Send verification email
        //             Mail::to($user->email)->send(new OtpMail($verificationToken));
        //         } catch (\Exception $e) {
        //             Log::error('Failed to send verification email: ' . $e->getMessage());
        //         }
        //     }
            
        //     return response()->json([
        //         "message" => "Akun belum diverifikasi. Link verifikasi telah dikirim ke email Anda.",
        //         "redirect" => route('auth.verifyOtpPage')
        //     ], 403);
        // }

        $token = $user->createToken($user->email)->plainTextToken;

        $data = [
            "message" => "Login Berhasil",
            "token" => $token,
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
     *     path="/api/auth/register/penitip",
     *     tags={"Authentication"},
     *     summary="User registration",
     *     description="Register a new user (penitip)",
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
     *     @OA\Response(response=201, description="User berhasil register"),
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
        
        // Generate UUID for verification
        $verificationToken = Str::uuid();
    
        // Store token in the database
        $otpCode = Otp::create([
            'user_id' => $user->id,
            'otp' => $verificationToken,
            'expires_at' => Carbon::now()->addHours(24), // Link valid for 24 hours
        ]);

        try {
            // Send verification email
            Mail::to($user->email)->send(new OtpMail($verificationToken));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send verification email: '.$e->getMessage()], 500);
        }

        $data = [
            'message' => 'User berhasil register. Silakan periksa email Anda untuk verifikasi.'
        ];

        return response()->json($data, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register/pengguna",
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
     *     @OA\Response(response=201, description="User berhasil register"),
     *     @OA\Response(response=400, description="Daftar sebagai pengguna harus menggunakan email ITK")
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

        // Generate UUID for verification
        $verificationToken = Str::uuid();
    
        // Store token in the database
        $otpCode = Otp::create([
            'user_id' => $user->id,
            'otp' => $verificationToken,
            'expires_at' => Carbon::now()->addHours(24), // Link valid for 24 hours
        ]);

        try {
            // Send verification email
            Mail::to($user->email)->send(new OtpMail($verificationToken));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send verification email: '.$e->getMessage()], 500);
        }

        $data = [
            'message' => 'User berhasil register. Silakan periksa email Anda untuk verifikasi.'
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

    // public function sendOtp(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);
    
    //     $user = User::where('email', $request->email)->first();
    
    //     if (!$user) {
    //         return response()->json(['message' => 'User not found'], 404);
    //     }
    
    //     // Generate UUID for verification
    //     $verificationToken = Str::uuid();
    
    //     // Delete any existing tokens for this user
    //     Otp::where('user_id', $user->id)->delete();
        
    //     // Store token in the database
    //     Otp::create([
    //         'user_id' => $user->id,
    //         'otp' => $verificationToken,
    //         'expires_at' => Carbon::now()->addHours(24), // Link valid for 24 hours
    //     ]);
    
    //     try {
    //         // Send verification email
    //         Mail::to($user->email)->send(new OtpMail($verificationToken));
    //         return response()->json(['message' => 'Verification link sent successfully']);
    //     } catch (\Exception $e) {
    //         Log::error('Failed to send verification email: ' . $e->getMessage());
    //         return response()->json(['message' => 'Failed to send verification email: '.$e->getMessage()], 500);
    //     }
    // }
    
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string',
        ]);

        $user = $request->user(); // Get the authenticated user from the token

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $otp = Otp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired verification link'], 400);
        }

        // Token is valid, delete it and update the user's is_verified field
        $otp->delete();
        $user->is_verified = true;
        $user->save();

        return response()->json(['message' => 'Email verification successful']);
    }

    /**
     * Handle verification link click
     */
    public function verifyEmail(Request $request, $token)
    {
        $otp = Otp::where('otp', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired verification link'], 400);
        }

        $user = User::find($otp->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Token is valid, verify the user
        $user->is_verified = true;
        $user->save();
        
        // Delete the token
        $otp->delete();

        return response()->json(['message' => 'Email verification successful']);
    }

    public function verifyOtpPage()
    {
        return response()->json([
            "message" => "Redirect to OTP verification page."
        ]);
    }



    /**
     * @OA\Put(
     *     path="/api/auth/password",
     *     tags={"Authentication"},
     *     summary="Change User Password",
     *     description="Change user password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"old_password","new_password", "new_password_confirmation"},
     *             @OA\Property(property="old_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password Change Successfully"),
     *     @OA\Response(response=400, description="Old password is incorrect"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed|min:8',
        ]);

        $user = $request->user();

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Old password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }
    
}
