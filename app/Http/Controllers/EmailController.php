<?php

namespace App\Http\Controllers;

use App\Mail\MailCP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class EmailController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = rand(100000, 999999);

        // Store OTP (replace if it exists)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $otp, 'created_at' => Carbon::now()]
        );

        // Send the email
        Mail::to($request->email)->send(new MailCP($otp));

        return response()->json([
            'message' => 'OTP sent successfully'
        ]);
    }

    // public function sendOtp(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //     ]);

    //     $otp = rand(100000, 999999);

    //     // Send the email
    //     Mail::to($request->email)->send(new MailCP($otp));

    //     return response()->json([
    //         'message' => 'OTP sent successfully',
    //         'otp' => $otp // Remove this line in production to keep OTP secret
    //     ]);
    // }

    public function resetPasswordWithOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $otpRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->otp)
            ->first();

        if (!$otpRecord || Carbon::parse($otpRecord->created_at)->addMinutes(10)->isPast()) {
            return response()->json(['error' => 'Invalid or expired OTP'], 400);
        }

        // Reset the password
        DB::table('users')
            ->where('email', $request->email)
            ->update([
                'password' => Hash::make($request->new_password),
            ]);

        // Optionally delete the used OTP
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully']);
    }
}
