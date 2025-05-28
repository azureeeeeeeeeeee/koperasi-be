<?php

namespace App\Http\Controllers;

use App\Models\Config as ModelsConfig;
use Midtrans\Snap;
use Midtrans\Config;
use Midtrans\CoreApi;
use App\Models\PaymentGateway;
use Faker\Provider\ar_EG\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentGatewayController extends Controller
{
    
    /**
     * @OA\Post(
     *     path="/api/payment/create-payment",
     *     summary="Create a new payment using QRIS or payment link",
     *     tags={"Payment Gateway"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "payment_method", "amount"},
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 example=1,
     *                 description="ID of the user making the payment"
     *             ),
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="string",
     *                 enum={"qris", "link"},
     *                 example="qris",
     *                 description="Payment method to use (qris or link)"
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payment_url",
     *                 type="string",
     *                 example="https://app.sandbox.midtrans.com/snap/v2/vtweb/ABC123",
     *                 description="Redirect URL for payment (QR code or Snap payment link)"
     *             ),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 description="Payment details",
     *                 @OA\Property(property="transaction_id", type="string", example="txn_654321"),
     *                 @OA\Property(property="order_id", type="string", example="ORD-123456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="payment_method", type="string", example="qris"),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_date", type="string", format="date-time", example="2025-05-05T14:30:00Z"),
     *                 @OA\Property(property="amount", type="number", format="float", example=100000)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Something went wrong"),
     *             @OA\Property(property="details", type="string", example="Payment error: Server timeout")
     *         )
     *     )
     * )
     */

    
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
             'payment_method' => 'required|string|in:qris,link', // allow both
             'amount' => 'required|numeric|min:0',
         ]);
     
         // Generate unique numeric order_id
         $order_id = 'ORD-' . rand(100000, 999999);
     
         // Common transaction params
         $transactionDetails = [
             'order_id' => $order_id,
             'gross_amount' => $validatedData['amount'],
         ];
     
         $customerDetails = [
             'first_name' => 'User',
             'last_name' => 'Example',
             'email' => 'user@example.com',
         ];
     
         try {
             $charge = null;
             $paymentUrl = null;
     
             if ($validatedData['payment_method'] === 'qris') {
                 // Use Core API for QRIS
                 $params = [
                     'payment_type' => 'qris',
                     'transaction_details' => $transactionDetails,
                     'customer_details' => $customerDetails,
                 ];
     
                 $charge = CoreApi::charge($params);
     
                 // Get QR URL from action
                 foreach ($charge->actions as $action) {
                     if ($action->name === 'generate-qr-code') {
                         $paymentUrl = $action->url;
                         break;
                     }
                 }
             } else {
                 // Use Snap API for "link" method
                 $params = [
                     'transaction_details' => $transactionDetails,
                     'customer_details' => $customerDetails,
                     // optionally: 'enabled_payments' => ['gopay', 'bank_transfer', ...],
                 ];
     
                 $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
                 $paymentUrl = $snapUrl;
     
                 // Simulate a successful charge object (Snap doesn't return full CoreApi data)
                 $charge = (object)[
                     'transaction_id' => uniqid('txn_'),
                     'transaction_status' => 'pending',
                 ];
             }
     
             // Save to database
             $payment = PaymentGateway::create([
                 'transaction_id' => $charge->transaction_id,
                 'order_id' => $order_id,
                 'user_id' => $validatedData['user_id'],
                 'payment_method' => $validatedData['payment_method'],
                 'payment_status' => $charge->transaction_status ?? 'pending',
                 'payment_date' => now(),
                 'amount' => $validatedData['amount'],
             ]);
     
             return response()->json([
                 'payment_url' => $paymentUrl,
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
     

    /**
     * @OA\Post(
     *     path="/api/payment/check-payment-status",
     *     summary="Check the status of a payment",
     *     tags={"Payment Gateway"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id"},
     *             @OA\Property(property="order_id", type="string", example="ORD-123456", description="Order ID of the payment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="order_id", type="string", example="ORD-123456"),
     *             @OA\Property(property="payment_status", type="string", example="settlement"),
     *             @OA\Property(property="transaction_id", type="string", example="abc123"),
     *             @OA\Property(property="payment_date", type="string", example="2023-01-01T12:00:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unable to fetch payment status"),
     *             @OA\Property(property="details", type="string", example="Error details")
     *         )
     *     )
     * )
     */

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
            
                // If paid successfully (settlement or capture), activate user and update cart status
                if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                    \App\Models\User::where('id', $payment->user_id)->update([
                        'status_keanggotaan' => 'aktif'
                    ]);
            
                    // Update cart status to "Paid"
                    $cart = \App\Models\Cart::where('id', $payment->cart_id)->first();
                    if ($cart && $cart->status !== 'Paid') {
                        $cart->status = 'Paid';
                        $cart->sudah_bayar = 1;
                        $cart->save();
                    }
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

    /**
     * @OA\Post(
     *     path="/api/payment/pay-for-membership",
     *     summary="Pay for membership",
     *     tags={"Payment Gateway"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "payment_method", "amount"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID of the user"),
     *             @OA\Property(property="payment_method", type="string", enum={"qris", "link"}, example="qris", description="Payment method"),
     *             @OA\Property(property="amount", type="number", format="float", example=10000, description="Membership fee amount")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Membership payment initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment initiated"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="order_id", type="string", example="MEMB-123456"),
     *             @OA\Property(property="payment_url", type="string", example="https://example.com/payment-link"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to process payment"),
     *             @OA\Property(property="details", type="string", example="Error details")
     *         )
     *     )
     * )
     */

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
             'payment_method' => 'required|string|in:qris,link',
         ]);
     
         // Get user
         $user = \App\Models\User::find($validated['user_id']);

         $config = ModelsConfig::where('key', 'iuran wajib')->first();
         $amount = $config ? (int) $config->value : 15000; 
     
         // Generate unique order_id
         $order_id = 'MEMB-' . uniqid();
     
         try {
             $charge = null;
             $paymentUrl = null;
     
             if ($validated['payment_method'] === 'qris') {
                 $params = [
                     'payment_type' => 'qris',
                     'transaction_details' => [
                         'order_id' => $order_id,
                         'gross_amount' => $amount,
                     ],
                     'customer_details' => [
                         'first_name' => explode(' ', $user->fullname)[0],
                         'last_name' => explode(' ', $user->fullname)[1] ?? '',
                         'email' => $user->email,
                     ]
                 ];
     
                 $charge = \Midtrans\CoreApi::charge($params);
     
                 // Get QR URL
                 foreach ($charge->actions ?? [] as $action) {
                     if ($action->name === 'generate-qr-code') {
                         $paymentUrl = $action->url;
                         break;
                     }
                 }
             } else {
                 // Use Snap API for 'link'
                 $params = [
                     'transaction_details' => [
                         'order_id' => $order_id,
                         'gross_amount' => $amount,
                     ],
                     'customer_details' => [
                         'first_name' => explode(' ', $user->fullname)[0],
                         'last_name' => explode(' ', $user->fullname)[1] ?? '',
                         'email' => $user->email,
                     ],
                 ];
     
                 $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
                 $paymentUrl = $snapUrl;
     
                 $charge = (object)[
                     'transaction_id' => uniqid('txn_'),
                     'transaction_status' => 'pending',
                 ];
             }
     
             \App\Models\PaymentGateway::create([
                 'transaction_id' => $charge->transaction_id,
                 'order_id' => $order_id,
                 'user_id' => $user->id,
                 'payment_method' => $validated['payment_method'],
                 'payment_status' => $charge->transaction_status,
                 'payment_date' => now(),
                 'amount' => $amount,
             ]);
     
             if ($charge->transaction_status === 'settlement') {
                 $user->status_keanggotaan = 'aktif';
                 $user->save();
             }
     
             return response()->json([
                 'message' => 'Payment initiated',
                 'status' => $charge->transaction_status ?? 'pending',
                 'order_id' => $order_id,
                 'payment_url' => $paymentUrl,
             ], 201);
     
         } catch (\Exception $e) {
             \Log::error('Membership payment error: ' . $e->getMessage());
             return response()->json([
                 'error' => 'Failed to process payment',
                 'details' => $e->getMessage()
             ], 500);
         }
     }
     

    /**
     * @OA\Post(
     *     path="/api/payment/pay-for-cart",
     *     summary="Pay for a cart",
     *     tags={"Payment Gateway"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cart_id", "payment_method", "amount"},
     *             @OA\Property(property="cart_id", type="integer", example=1, description="ID of the cart"),
     *             @OA\Property(property="payment_method", type="string", enum={"qris", "link"}, example="qris", description="Payment method"),
     *             @OA\Property(property="amount", type="number", format="float", example=50000, description="Cart payment amount")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cart payment initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment initiated"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="order_id", type="string", example="CART-123456"),
     *             @OA\Property(property="payment_url", type="string", example="https://example.com/payment-link"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to process payment"),
     *             @OA\Property(property="details", type="string", example="Error details")
     *         )
     *     )
     * )
     */


     public function payForCart(Request $request)
     {
         // Midtrans setup
         \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
         \Midtrans\Config::$isProduction = false;
         \Midtrans\Config::$isSanitized = true;
         \Midtrans\Config::$is3ds = true;
     
         // Validate input
         $validated = $request->validate([
             'cart_id' => 'required|exists:carts,id',
             'payment_method' => 'required|string|in:qris,link',
             'amount' => 'required|numeric|min:1000',
         ]);
     
         // Get cart
         $cart = \App\Models\Cart::find($validated['cart_id']);
     
         // Generate unique order_id
         $order_id = 'CART-' . uniqid();
     
         try {
             $charge = null;
             $paymentUrl = null;
     
             if ($validated['payment_method'] === 'qris') {
                 $params = [
                     'payment_type' => 'qris',
                     'transaction_details' => [
                         'order_id' => $order_id,
                         'gross_amount' => $validated['amount'],
                     ],
                     'customer_details' => [
                         'first_name' => $cart->user->name,
                         'email' => $cart->user->email,
                     ]
                 ];
     
                 $charge = \Midtrans\CoreApi::charge($params);
     
                 foreach ($charge->actions ?? [] as $action) {
                     if ($action->name === 'generate-qr-code') {
                         $paymentUrl = $action->url;
                         break;
                     }
                 }
             } else {
                 // Use Snap API for 'link'
                 $params = [
                     'transaction_details' => [
                         'order_id' => $order_id,
                         'gross_amount' => $validated['amount'],
                     ],
                     'customer_details' => [
                         'first_name' => $cart->user->name,
                         'email' => $cart->user->email,
                     ]
                 ];
     
                 $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
                 $paymentUrl = $snapUrl;
     
                 $charge = (object)[
                     'transaction_id' => uniqid('txn_'),
                     'transaction_status' => 'pending',
                 ];
             }
     
             \App\Models\PaymentGateway::create([
                 'transaction_id' => $charge->transaction_id,
                 'order_id' => $order_id,
                 'user_id' => $cart->user_id,
                 'cart_id' => $cart->id,
                 'payment_method' => $validated['payment_method'],
                 'payment_status' => $charge->transaction_status,
                 'payment_date' => now(),
                 'amount' => $validated['amount'],
             ]);
     
             $cart->status = 'Menunggu pembayaran';
             $cart->save();
     
             return response()->json([
                 'message' => 'Payment initiated',
                 'status' => $charge->transaction_status ?? 'pending',
                 'order_id' => $order_id,
                 'payment_url' => $paymentUrl,
             ], 201);
     
         } catch (\Exception $e) {
             \Log::error('Cart payment error: ' . $e->getMessage());
             return response()->json([
                 'error' => 'Failed to process payment',
                 'details' => $e->getMessage()
             ], 500);
         }
     }
     

    public function topUp(Request $request)
    {
        // Midtrans setup
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Validasi input
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_method' => 'required|string|in:qris,link',
            'amount' => 'required|numeric|min:1000',
        ]);

        // Ambil data user
        $user = \App\Models\User::find($validated['user_id']);

        // Periksa apakah user bertipe "pengguna"
        if ($user->tipe !== 'pengguna') {
            return response()->json([
                'error' => 'Top-up hanya tersedia untuk akun bertipe pengguna'
            ], 403);
        }

        // Generate unique order_id
        $order_id = 'TOPUP-' . uniqid();

        try {
            $charge = null;
            $paymentUrl = null;

            if ($validated['payment_method'] === 'qris') {
                // Gunakan Core API untuk QRIS
                $params = [
                    'payment_type' => 'qris',
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

                $charge = \Midtrans\CoreApi::charge($params);

                // Ambil URL QR dari actions
                foreach ($charge->actions ?? [] as $action) {
                    if ($action->name === 'generate-qr-code') {
                        $paymentUrl = $action->url;
                        break;
                    }
                }
            } else {
                // Gunakan Snap API untuk metode "link"
                $params = [
                    'transaction_details' => [
                        'order_id' => $order_id,
                        'gross_amount' => $validated['amount'],
                    ],
                    'customer_details' => [
                        'first_name' => explode(' ', $user->fullname)[0],
                        'last_name' => explode(' ', $user->fullname)[1] ?? '',
                        'email' => $user->email,
                    ],
                ];

                $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
                $paymentUrl = $snapUrl;

                $charge = (object)[
                    'transaction_id' => uniqid('txn_'),
                    'transaction_status' => 'pending',
                ];
            }

            // Simpan data pembayaran ke database
            \App\Models\PaymentGateway::create([
                'transaction_id' => $charge->transaction_id,
                'order_id' => $order_id,
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $charge->transaction_status,
                'payment_date' => now(),
                'amount' => $validated['amount'],
            ]);

            // Tambahkan saldo jika pembayaran berhasil
            if (in_array($charge->transaction_status, ['settlement', 'capture'])) {
                $user->saldo += $validated['amount'];
                $user->save();
            }

            return response()->json([
                'message' => 'Top-up initiated',
                'status' => $charge->transaction_status ?? 'pending',
                'order_id' => $order_id,
                'payment_url' => $paymentUrl,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Top-up error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to process top-up',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * @OA\Get(
     *     path="/api/payment/transactions",
     *     summary="Get list of transactions",
     *     description="Fetches all transactions. Admin sees all transactions, while users see only their own.",
     *     tags={"Payment Gateway"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Transaction list fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Transaction list fetched successfully"),
     *             @OA\Property(
     *                 property="transactions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="transaction_id", type="string", example="txn_123456789"),
     *                     @OA\Property(property="order_id", type="string", example="CART-123456"),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="cart_id", type="integer", example=2),
     *                     @OA\Property(property="payment_method", type="string", example="qris"),
     *                     @OA\Property(property="payment_status", type="string", example="pending"),
     *                     @OA\Property(property="payment_date", type="string", format="date-time", example="2025-05-20T12:00:00Z"),
     *                     @OA\Property(property="amount", type="number", format="float", example=50000),
     *                     @OA\Property(
     *                         property="cart",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@example.com")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function getTransactions(Request $request)
    {
        $user = $request->user();

        // if (!in_array($user->tipe, ['admin', 'pengguna', 'penitip'])) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        if ($user->tipe === 'admin') {
            $transactions = \App\Models\PaymentGateway::with(['cart.user'])->latest()->get();

        } elseif (in_array($user->tipe, ['pengguna', 'penitip'])) {
            $transactions = \App\Models\PaymentGateway::whereHas('cart', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->with(['cart.user'])->latest()->get();
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Transaction list fetched successfully',
            'transactions' => $transactions,
        ]);
    }

}
