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
        // Validate the input (ensure order_id is provided)
        $validatedData = $request->validate([
            'order_id' => 'required|string', // Ensure order_id is a string
        ]);

        try {
            // Fetch the order ID from the request
            $orderId = $validatedData['order_id'];

            // Setup Midtrans configuration
            Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            Config::$isProduction = false; // Set to true for production
            Config::$isSanitized = true;
            Config::$is3ds = true;

            // Call Midtrans API for transaction status
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', "https://api.sandbox.midtrans.com/v2/{$orderId}/status", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(env('MIDTRANS_SERVER_KEY') . ':'),
                ]
            ]);

            $status = json_decode($response->getBody()->getContents());

            // If the status is found, return it
            return response()->json([
                'order_id' => $orderId,
                'payment_status' => $status->transaction_status ?? 'unknown',
                'transaction_id' => $status->transaction_id ?? null,
                'payment_date' => $status->transaction_time ?? null,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Payment status error: ' . $e->getMessage());

            // Return error if the payment status check fails
            return response()->json([
                'error' => 'Unable to fetch payment status',
                'details' => $e->getMessage(),
            ], 500);
        }
    }



}
