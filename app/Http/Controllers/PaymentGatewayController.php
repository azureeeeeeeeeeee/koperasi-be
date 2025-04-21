<?php

namespace App\Http\Controllers;

use Midtrans\Snap;
use Midtrans\Config;
use Midtrans\CoreApi;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    
    public function createPayment(Request $request)
    {
        // Setup Midtrans config
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        // Validate input
        $validatedData = $request->validate([
            'user_id' => 'required|integer',
            'payment_method' => 'required|string|in:qris', // You can allow more types if needed
            'amount' => 'required|numeric|min:0',
        ]);

        // Generate unique numeric order_id
        $order_id = 'ORD-' . rand(100000, 999999);

        // Prepare Midtrans charge params
        $params = [
            'payment_type' => $validatedData['payment_method'],
            'transaction_details' => [
                'order_id' => $order_id,
                'gross_amount' => $validatedData['amount'],
            ],
            'customer_details' => [
                'first_name' => 'User',
                'last_name' => 'Example',
                'email' => 'user@example.com',
            ]
        ];

        try {
            // Call Core API to charge
            $charge = CoreApi::charge($params);

            // Get QRIS URL (if QRIS used)
            $qrUrl = null;
            if (isset($charge->actions)) { // Use object notation
                foreach ($charge->actions as $action) {
                    if ($action->name === 'generate-qr-code') { // Use object notation
                        $qrUrl = $action->url; // Use object notation
                        break;
                    }
                }
            }

            // Save to database
            $payment = PaymentGateway::create([
                'transaction_id' => $charge->transaction_id, // Use object notation
                'order_id' => $order_id,
                'user_id' => $validatedData['user_id'],
                'payment_method' => $validatedData['payment_method'],
                'payment_status' => $charge->transaction_status ?? 'pending', // Use object notation
                'payment_date' => now(),
                'amount' => $validatedData['amount'],
            ]);

            // Return QRIS URL + payment
            return response()->json([
                'qris_url' => $qrUrl,
                'payment' => $payment
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Payment error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|string',
        ]);

        try {
            $orderId = $validatedData['order_id'];

            // Midtrans config
            Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            Config::$isProduction = false;
            Config::$isSanitized = true;
            Config::$is3ds = true;

            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://api.sandbox.midtrans.com/v2/{$orderId}/status", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(env('MIDTRANS_SERVER_KEY') . ':'),
                ]
            ]);

            $status = json_decode($response->getBody()->getContents());

            // Update payment status in DB
            $payment = \App\Models\PaymentGateway::where('order_id', $orderId)->first();

            if ($payment) {
                $payment->payment_status = $status->transaction_status ?? 'unknown';
                $payment->save();

                // If paid successfully (settlement or capture), activate user
                if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                    \App\Models\User::where('id', $payment->user_id)->update([
                        'status_keanggotaan' => 'aktif'
                    ]);
                }
            }

            return response()->json([
                'order_id' => $orderId,
                'payment_status' => $status->transaction_status ?? 'unknown',
                'transaction_id' => $status->transaction_id ?? null,
                'payment_date' => $status->transaction_time ?? null,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Payment status error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Unable to fetch payment status',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function payForMembership(Request $request)
    {
        // Midtrans setup
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Validate input
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_method' => 'required|string|in:qris',
            'amount' => 'required|numeric|min:10000', // example: Rp 10.000 membership fee
        ]);

        // Get user
        $user = \App\Models\User::find($validated['user_id']);

        // Generate unique order_id
        $order_id = 'MEMB-' . uniqid();

        // Prepare payment payload
        $params = [
            'payment_type' => $validated['payment_method'],
            'transaction_details' => [
                'order_id' => $order_id,
                'gross_amount' => $validated['amount'],
            ],
            'customer_details' => [
                'first_name' => explode(' ', $user->fullname)[0],
                'last_name' => explode(' ', $user->fullname)[1] ?? '',
                'email' => $user->email,
            ]
        ];

        try {
            // Send to Midtrans
            $charge = \Midtrans\CoreApi::charge($params);

            // Save payment to DB
            \App\Models\PaymentGateway::create([
                'transaction_id' => $charge->transaction_id,
                'order_id' => $order_id,
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $charge->transaction_status,
                'payment_date' => now(),
                'amount' => $validated['amount'],
            ]);

            // If settled, activate user
            if ($charge->transaction_status === 'settlement') {
                $user->status_keanggotaan = 'aktif';
                $user->save();
            }

            // Return QR code (if available) and status
            $qrUrl = null;
            foreach ($charge->actions ?? [] as $action) {
                if ($action->name === 'generate-qr-code') {
                    $qrUrl = $action->url;
                    break;
                }
            }

            return response()->json([
                'message' => 'Payment initiated',
                'status' => $charge->transaction_status ?? 'pending',
                'order_id' => $order_id,
                'qris_url' => $qrUrl,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Membership payment error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to process payment',
                'details' => $e->getMessage()
            ], 500);
        }
    }

}
