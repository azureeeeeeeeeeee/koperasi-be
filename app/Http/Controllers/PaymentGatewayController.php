<?php

namespace App\Http\Controllers;

use App\Models\Cart;
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
    // Flexible payment processor for all payment types
    private function processPayment($user, $order_id, $amount, $payment_method, $extra = [])
    {
        $transactionDetails = [
            'order_id' => $order_id,
            'gross_amount' => $amount,
        ];

        $customerDetails = [
            'first_name' => explode(' ', $user->fullname)[0] ?? $user->name ?? 'User',
            'last_name' => explode(' ', $user->fullname)[1] ?? '',
            'email' => $user->email,
            'phone' => $extra['phone'] ?? $user->phone ?? null,
        ];

        $charge = null;
        $paymentUrl = null;
        $extraData = [];

        switch ($payment_method) {
            case 'qris':
                $params = [
                    'payment_type' => 'qris',
                    'transaction_details' => $transactionDetails,
                    'customer_details' => $customerDetails,
                ];
                $charge = CoreApi::charge($params);
                foreach ($charge->actions ?? [] as $action) {
                    if ($action->name === 'generate-qr-code') {
                        $paymentUrl = $action->url;
                        break;
                    }
                }
                break;

            case 'gopay':
                $params = [
                    'payment_type' => 'gopay',
                    'transaction_details' => $transactionDetails,
                    'customer_details' => $customerDetails,
                    'gopay' => [
                        'enable_callback' => false,
                        'callback_url' => '',
                    ],
                ];
                $charge = CoreApi::charge($params);
                $paymentUrl = $charge->actions[1]->url ?? $charge->actions[0]->url ?? null;
                break;

            case 'link':
                $params = [
                    'transaction_details' => $transactionDetails,
                    'customer_details' => $customerDetails,
                ];
                $snapUrl = Snap::createTransaction($params)->redirect_url;
                $paymentUrl = $snapUrl;
                $charge = (object)[
                    'transaction_id' => uniqid('txn_'),
                    'transaction_status' => 'pending',
                ];
                break;

            case 'bank':
                $bank = $extra['bank'] ?? null;
                if (!in_array($bank, ['bni', 'mandiri', 'bri'])) {
                    throw new \Exception('Bank not supported for virtual account');
                }
                $params = [
                    'payment_type' => 'bank_transfer',
                    'transaction_details' => $transactionDetails,
                    'customer_details' => $customerDetails,
                    'bank_transfer' => [
                        'bank' => $bank,
                    ],
                ];
                $charge = CoreApi::charge($params);
                $vaNumbers = $charge->va_numbers ?? [];
                $extraData['va_number'] = $vaNumbers[0]->va_number ?? null;
                $extraData['bank'] = $vaNumbers[0]->bank ?? $bank;
                $paymentUrl = null;
                break;

            case 'manual':
                $bank = $extra['bank'] ?? null;
                if (!in_array($bank, ['mandiri', 'bni', 'bri', 'bca'])) {
                    throw new \Exception('Bank not supported for manual transfer');
                }
                $charge = (object)[
                    'transaction_id' => uniqid('manual_'),
                    'transaction_status' => 'pending',
                ];
                $paymentUrl = null;
                $extraData['manual_bank'] = $bank;
                break;

            case 'iuran_wajib':
                $charge = (object)[
                    'transaction_id' => uniqid('iuran_'),
                    'transaction_status' => 'settlement',
                ];
                $paymentUrl = null;
                break;
        }

        return [
            'charge' => $charge,
            'paymentUrl' => $paymentUrl,
            'extraData' => $extraData,
        ];
    }

    // --- createPayment endpoint ---
    /**
     * @OA\Post(
     *     path="/api/payment/create-payment",
     *     summary="Create a new payment via Midtrans or manual/iuran methods",
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
     *                 enum={"qris", "gopay", "link", "bank", "manual", "iuran_wajib"},
     *                 example="qris",
     *                 description="Payment method to use"
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 example=100000,
     *                 description="Amount to be paid"
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 example="08123456789",
     *                 nullable=true,
     *                 description="Phone number (required for GoPay)"
     *             ),
     *             @OA\Property(
     *                 property="bank",
     *                 type="string",
     *                 enum={"bni", "mandiri", "bri", "bca"},
     *                 example="bni",
     *                 nullable=true,
     *                 description="Bank code (required for 'bank' or 'manual' methods)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payment_url",
     *                 type="string",
     *                 nullable=true,
     *                 example="https://app.sandbox.midtrans.com/snap/v2/vtweb/ABC123",
     *                 description="Redirect or QR code URL for payment"
     *             ),
     *             @OA\Property(
     *                 property="payment_va",
     *                 type="string",
     *                 nullable=true,
     *                 example="1234567890",
     *                 description="Virtual account number (if using 'bank' method)"
     *             ),
     *             @OA\Property(
     *                 property="payment_account",
     *                 type="string",
     *                 nullable=true,
     *                 example="bni",
     *                 description="Bank name for manual transfer (if using 'manual' method)"
     *             ),
     *             @OA\Property(
     *                 property="payment_ammount",
     *                 type="number",
     *                 format="float",
     *                 example=100000,
     *                 description="Total amount charged"
     *             ),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 description="Details of the saved payment",
     *                 @OA\Property(property="transaction_id", type="string", example="txn_654321"),
     *                 @OA\Property(property="order_id", type="string", example="ORD-123456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="payment_method", type="string", example="qris"),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_date", type="string", format="date-time", example="2025-05-05T14:30:00Z"),
     *                 @OA\Property(property="amount", type="number", format="float", example=100000)
     *             ),
     *             @OA\Property(
     *                 property="extra",
     *                 type="object",
     *                 nullable=true,
     *                 description="Optional additional payment info (e.g., VA number, bank name)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Bank not supported for virtual account")
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
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'payment_method' => 'required|string|in:qris,gopay,link,bank,manual,iuran_wajib',
            'amount' => 'required|numeric|min:0',
            'phone' => 'required_if:payment_method,gopay|nullable|string',
            'bank' => 'required_if:payment_method,bank,manual|nullable|string|in:bni,mandiri,bri,bca',
        ]);

        $user = \App\Models\User::find($validatedData['user_id']);
        $order_id = 'ORD-' . uniqid();

        try {
            $result = $this->processPayment(
                $user,
                $order_id,
                $validatedData['amount'],
                $validatedData['payment_method'],
                [
                    'bank' => $validatedData['bank'] ?? null,
                    'phone' => $validatedData['phone'] ?? null,
                ]
            );

            $payment = PaymentGateway::create([
                'transaction_id' => $result['charge']->transaction_id,
                'order_id' => $order_id,
                'user_id' => $validatedData['user_id'],
                'payment_method' => $validatedData['payment_method'],
                'payment_status' => $result['charge']->transaction_status ?? 'pending',
                'payment_date' => now(),
                'amount' => $validatedData['amount'],
                'extra' => !empty($result['extraData']) ? json_encode($result['extraData']) : null,
            ]);

            $response = [
                'payment_url' => $result['paymentUrl'],
                'payment_va' => $result['extraData']['va_number'] ?? null,
                'payment_account' => $result['extraData']['manual_bank'] ?? null,
                'payment_ammount' => $validatedData['amount'],
                'payment' => $payment,
            ];

            if (!empty($result['extraData'])) {
                $response['extra'] = $result['extraData'];
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            \Log::error('Payment error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // --- payForCart endpoint (with items, stock, COD, and all payment methods) ---
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
         \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
         \Midtrans\Config::$isProduction = false;
         \Midtrans\Config::$isSanitized = true;
         \Midtrans\Config::$is3ds = true;
     
         $validated = $request->validate([
             'cart_id' => 'required|exists:carts,id',
             'payment_method' => 'required|string|in:qris,link,bank,manual,gopay,cod',
             'bank' => 'required_if:payment_method,bank,manual|nullable|string|in:bni,mandiri,bri,bca',
             'phone' => 'required_if:payment_method,gopay|nullable|string',
             'items' => 'sometimes|array|min:1',
             'items.*.product_id' => 'required_with:items|exists:cart_item,product_id',
             'items.*.jumlah' => 'required_with:items|integer|min:1',
         ]);
     
         $cart = Cart::findOrFail($validated['cart_id']);
         $cart->load('products.category');
 
         // If items are provided, update cart items and stock
         if (isset($validated['items'])) {
             $selectedItemIds = collect($validated['items'])->pluck('product_id')->toArray();
             $cart->products()->detach(
                 $cart->products()->whereNotIn('product_id', $selectedItemIds)->pluck('product_id')
             );
 
             foreach ($validated['items'] as $itemData) {
                 $cart->products()->updateExistingPivot($itemData['product_id'], [
                     'jumlah' => $itemData['jumlah']
                 ]);
 
                 $product = \App\Models\Product::find($itemData['product_id']);
                 if ($product) {
                     $product->stock -= $itemData['jumlah'];
                     if ($product->stock < 0) {
                         return response()->json([
                             'error' => 'Stok produk tidak mencukupi untuk produk ID: ' . $itemData['product_id']
                         ], 422);
                     }
                     $product->save();
                 }
             }
 
             // Recalculate total
             $total = 0;
             foreach ($cart->products()->get() as $item) {
                 $potongan = $item->category->potongan ?? 0;
                 $markup = $item->price * ($potongan / 100);
                 $realPrice = $item->price + $markup;
                 $total += $realPrice * $item->pivot->jumlah;
             }
             $cart->total_harga = $total;
             $cart->save();
         }
 
         $user = $cart->user;
         $amount = $cart->total_harga;
         $order_id = 'CART-' . uniqid();
 
         try {
             if ($validated['payment_method'] === 'cod') {
                 PaymentGateway::create([
                     'transaction_id' => $order_id,
                     'order_id' => $order_id,
                     'user_id' => $cart->user_id ? $cart->user_id : $cart->guest_id,
                     'cart_id' => $cart->id,
                     'payment_method' => $validated['payment_method'],
                     'payment_status' => "settlement",
                     'payment_date' => now(),
                     'amount' => $cart->total_harga,
                 ]);
 
                 $cart->status_barang = 'akan dikirim';
                 $cart->sudah_bayar = true;
                 $cart->save();
 
                 return response()->json([
                     'message' => 'Payment initiated',
                     'status' => 'cod',
                     'order_id' => $order_id,
                 ], 201);
             }
 
             $result = $this->processPayment(
                 $user,
                 $order_id,
                 $amount,
                 $validated['payment_method'],
                 [
                     'bank' => $validated['bank'] ?? null,
                     'phone' => $validated['phone'] ?? null,
                 ]
             );
 
             PaymentGateway::create([
                 'transaction_id' => $result['charge']->transaction_id,
                 'order_id' => $order_id,
                 'user_id' => $cart->user_id,
                 'cart_id' => $cart->id,
                 'payment_method' => $validated['payment_method'],
                 'payment_status' => $result['charge']->transaction_status,
                 'payment_date' => now(),
                 'amount' => $amount,
                 'extra' => !empty($result['extraData']) ? json_encode($result['extraData']) : null,
             ]);
 
             $cart->status = 'Menunggu pembayaran';
             $cart->save();
 
             return response()->json([
                 'message' => 'Payment initiated',
                 'status' => $result['charge']->transaction_status ?? 'pending',
                 'order_id' => $order_id,
                 'payment_url' => $result['paymentUrl'],
                 'extra' => $result['extraData'] ?? null,
             ], 201);
 
         } catch (\Exception $e) {
             \Log::error('Cart payment error: ' . $e->getMessage());
             return response()->json([
                 'error' => 'Failed to process payment',
                 'details' => $e->getMessage()
             ], 500);
         }
     }

    // --- payForMembership (uses processPayment) ---
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
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_method' => 'required|string|in:qris,link,bank,manual,gopay,iuran_wajib',
            'bank' => 'required_if:payment_method,bank,manual|nullable|string|in:bni,mandiri,bri,bca',
            'phone' => 'required_if:payment_method,gopay|nullable|string',
        ]);

        $user = \App\Models\User::find($validated['user_id']);
        $config = ModelsConfig::where('key', 'iuran wajib')->first();
        $amount = $config ? (int) $config->value : 15000;
        $order_id = 'MEMB-' . uniqid();

        try {
            $result = $this->processPayment(
                $user,
                $order_id,
                $amount,
                $validated['payment_method'],
                [
                    'bank' => $validated['bank'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                ]
            );

            PaymentGateway::create([
                'transaction_id' => $result['charge']->transaction_id,
                'order_id' => $order_id,
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $result['charge']->transaction_status,
                'payment_date' => now(),
                'amount' => $amount,
                'extra' => !empty($result['extraData']) ? json_encode($result['extraData']) : null,
            ]);

            if ($result['charge']->transaction_status === 'settlement') {
                $user->status_keanggotaan = 'aktif';
                $user->save();
            }

            return response()->json([
                'message' => 'Payment initiated',
                'status' => $result['charge']->transaction_status ?? 'pending',
                'order_id' => $order_id,
                'payment_url' => $result['paymentUrl'],
                'extra' => $result['extraData'] ?? null,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Membership payment error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to process payment',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // --- topUp (unchanged) ---
    /**
     * @OA\Post(
     *     path="/api/payment/top-up",
     *     summary="Top up user balance",
     *     tags={"Payment Gateway"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "payment_method", "amount"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID of the user"),
     *             @OA\Property(property="payment_method", type="string", enum={"qris", "link"}, example="qris", description="Payment method"),
     *             @OA\Property(property="amount", type="number", format="float", example=10000, description="Top-up amount")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Top-up initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Top-up initiated"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="order_id", type="string", example="TOPUP-123456"),
     *             @OA\Property(property="payment_url", type="string", example="https://example.com/payment-link"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to process top-up"),
     *             @OA\Property(property="details", type="string", example="Error details")
     *         )
     *     )
     * )
     */

    public function topUp(Request $request)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_method' => 'required|string|in:qris,link',
            'amount' => 'required|numeric|min:1000',
        ]);

        $user = \App\Models\User::find($validated['user_id']);

        if ($user->tipe !== 'pengguna') {
            return response()->json([
                'error' => 'Top-up hanya tersedia untuk akun bertipe pengguna'
            ], 403);
        }

        $order_id = 'TOPUP-' . uniqid();

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
                        'first_name' => explode(' ', $user->fullname)[0],
                        'last_name' => explode(' ', $user->fullname)[1] ?? '',
                        'email' => $user->email,
                    ]
                ];

                $charge = CoreApi::charge($params);

                foreach ($charge->actions ?? [] as $action) {
                    if ($action->name === 'generate-qr-code') {
                        $paymentUrl = $action->url;
                        break;
                    }
                }
            } else {
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

                $snapUrl = Snap::createTransaction($params)->redirect_url;
                $paymentUrl = $snapUrl;

                $charge = (object)[
                    'transaction_id' => uniqid('txn_'),
                    'transaction_status' => 'pending',
                ];
            }

            PaymentGateway::create([
                'transaction_id' => $charge->transaction_id,
                'order_id' => $order_id,
                'user_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $charge->transaction_status,
                'payment_date' => now(),
                'amount' => $validated['amount'],
            ]);

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

    // --- checkPaymentStatus (unchanged) ---
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

            $payment = PaymentGateway::where('order_id', $orderId)->first();

            if ($payment) {
                $payment->payment_status = $status->transaction_status ?? 'unknown';
                $payment->save();

                if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                    \App\Models\User::where('id', $payment->user_id)->update([
                        'status_keanggotaan' => 'aktif'
                    ]);
                    $cart = Cart::where('id', $payment->cart_id)->first();
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

    // /**
    //  * @OA\Post(
    //  *     path="/api/payment/pay-for-membership",
    //  *     summary="Pay for membership",
    //  *     tags={"Payment Gateway"},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"user_id", "payment_method", "amount"},
    //  *             @OA\Property(property="user_id", type="integer", example=1, description="ID of the user"),
    //  *             @OA\Property(property="payment_method", type="string", enum={"qris", "link"}, example="qris", description="Payment method"),
    //  *             @OA\Property(property="amount", type="number", format="float", example=10000, description="Membership fee amount")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Membership payment initiated successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="message", type="string", example="Payment initiated"),
    //  *             @OA\Property(property="status", type="string", example="pending"),
    //  *             @OA\Property(property="order_id", type="string", example="MEMB-123456"),
    //  *             @OA\Property(property="payment_url", type="string", example="https://example.com/payment-link"),
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=500,
    //  *         description="Internal server error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="error", type="string", example="Failed to process payment"),
    //  *             @OA\Property(property="details", type="string", example="Error details")
    //  *         )
    //  *     )
    //  * )
    //  */

    //  public function payForMembership(Request $request)
    //  {
    //      // Midtrans setup
    //      \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
    //      \Midtrans\Config::$isProduction = false;
    //      \Midtrans\Config::$isSanitized = true;
    //      \Midtrans\Config::$is3ds = true;
     
    //      // Validate input
    //      $validated = $request->validate([
    //          'user_id' => 'required|exists:users,id',
    //          'payment_method' => 'required|string|in:qris,link',
    //      ]);
     
    //      // Get user
    //      $user = \App\Models\User::find($validated['user_id']);

    //      $config = ModelsConfig::where('key', 'iuran wajib')->first();
    //      $amount = $config ? (int) $config->value : 15000; 
     
    //      // Generate unique order_id
    //      $order_id = 'MEMB-' . uniqid();
     
    //      try {
    //          $charge = null;
    //          $paymentUrl = null;
     
    //          if ($validated['payment_method'] === 'qris') {
    //              $params = [
    //                  'payment_type' => 'qris',
    //                  'transaction_details' => [
    //                      'order_id' => $order_id,
    //                      'gross_amount' => $amount,
    //                  ],
    //                  'customer_details' => [
    //                      'first_name' => explode(' ', $user->fullname)[0],
    //                      'last_name' => explode(' ', $user->fullname)[1] ?? '',
    //                      'email' => $user->email,
    //                  ]
    //              ];
     
    //              $charge = \Midtrans\CoreApi::charge($params);
     
    //              // Get QR URL
    //              foreach ($charge->actions ?? [] as $action) {
    //                  if ($action->name === 'generate-qr-code') {
    //                      $paymentUrl = $action->url;
    //                      break;
    //                  }
    //              }
    //          } else {
    //              // Use Snap API for 'link'
    //              $params = [
    //                  'transaction_details' => [
    //                      'order_id' => $order_id,
    //                      'gross_amount' => $amount,
    //                  ],
    //                  'customer_details' => [
    //                      'first_name' => explode(' ', $user->fullname)[0],
    //                      'last_name' => explode(' ', $user->fullname)[1] ?? '',
    //                      'email' => $user->email,
    //                  ],
    //              ];
     
    //              $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
    //              $paymentUrl = $snapUrl;
     
    //              $charge = (object)[
    //                  'transaction_id' => uniqid('txn_'),
    //                  'transaction_status' => 'pending',
    //              ];
    //          }
     
    //          \App\Models\PaymentGateway::create([
    //              'transaction_id' => $charge->transaction_id,
    //              'order_id' => $order_id,
    //              'user_id' => $user->id,
    //              'payment_method' => $validated['payment_method'],
    //              'payment_status' => $charge->transaction_status,
    //              'payment_date' => now(),
    //              'amount' => $amount,
    //          ]);
     
    //          if ($charge->transaction_status === 'settlement') {
    //              $user->status_keanggotaan = 'aktif';
    //              $user->save();
    //          }
     
    //          return response()->json([
    //              'message' => 'Payment initiated',
    //              'status' => $charge->transaction_status ?? 'pending',
    //              'order_id' => $order_id,
    //              'payment_url' => $paymentUrl,
    //          ], 201);
     
    //      } catch (\Exception $e) {
    //          \Log::error('Membership payment error: ' . $e->getMessage());
    //          return response()->json([
    //              'error' => 'Failed to process payment',
    //              'details' => $e->getMessage()
    //          ], 500);
    //      }
    //  }
     

    // /**
    //  * @OA\Post(
    //  *     path="/api/payment/pay-for-cart",
    //  *     summary="Pay for a cart",
    //  *     tags={"Payment Gateway"},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"cart_id", "payment_method", "items"},
    //  *             @OA\Property(property="cart_id", type="integer", example=1, description="ID of the cart"),
    //  *             @OA\Property(property="payment_method", type="string", enum={"qris", "link", "cod"}, example="qris", description="Payment method"),
    //  *             @OA\Property(
    //  *                 property="items",
    //  *                 type="array",
    //  *                 description="List of selected items with quantity",
    //  *                 @OA\Items(
    //  *                     type="object",
    //  *                     required={"product_id", "jumlah"},
    //  *                     @OA\Property(property="product_id", type="integer", example=101),
    //  *                     @OA\Property(property="jumlah", type="integer", example=2)
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="Cart payment initiated successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="message", type="string", example="Payment initiated"),
    //  *             @OA\Property(property="status", type="string", example="pending"),
    //  *             @OA\Property(property="order_id", type="string", example="CART-123456"),
    //  *             @OA\Property(property="payment_url", type="string", nullable=true, example="https://example.com/payment-link")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Validation or business logic error (e.g. out of stock)",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="error", type="string", example="Stok produk tidak mencukupi untuk produk ID: 5")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=500,
    //  *         description="Internal server error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="error", type="string", example="Failed to process payment"),
    //  *             @OA\Property(property="details", type="string", example="Error details")
    //  *         )
    //  *     )
    //  * )
    //  */
    //  public function payForCart(Request $request)
    //  {
    //      // Midtrans setup
    //      \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
    //      \Midtrans\Config::$isProduction = false;
    //      \Midtrans\Config::$isSanitized = true;
    //      \Midtrans\Config::$is3ds = true;
     
    //      // Validate input
    //      $validated = $request->validate([
    //         'cart_id' => 'required|exists:carts,id',
    //         'payment_method' => 'required|string|in:qris,link,cod',
    //         'items' => 'required|array|min:1',
    //         'items.*.product_id' => 'required|exists:cart_item,product_id',
    //         'items.*.jumlah' => 'required|integer|min:1',
    //       ]);
     
    //     $cart = Cart::findOrFail($validated['cart_id']);
    //     $cart->load('products.category');

    //     $selectedItemIds = collect($validated['items'])->pluck('product_id')->toArray();
    //     $cart->products()->detach(
    //         $cart->products()->whereNotIn('product_id', $selectedItemIds)->pluck('product_id')
    //     );

    //     foreach ($validated['items'] as $itemData) {
    //         $cart->products()->updateExistingPivot($itemData['product_id'], [
    //             'jumlah' => $itemData['jumlah']
    //         ]);
        
    //         $product = \App\Models\Product::find($itemData['product_id']);
    //         if ($product) {
    //             $product->stock -= $itemData['jumlah'];
    //             if ($product->stock < 0) {
    //                 return response()->json([
    //                     'error' => 'Stok produk tidak mencukupi untuk produk ID: ' . $itemData['product_id']
    //                 ], 422);
    //             }
    //             $product->save();
    //         }
    //     }

    //     // $selectedItems = $cart->products()->whereIn('product_id', $selectedItemIds)->with('product')->get();

    //     $total = 0;
    //     // foreach ($selectedItems as $item) {
    //     foreach ($cart->products()->get() as $item) {
    //         $potongan = $item->category->potongan ?? 0;
    //         $markup = $item->price * ($potongan / 100);
    //         $realPrice = $item->price + $markup;
    //         $total += $realPrice * $item->pivot->jumlah;
    //     }

    //     $cart->total_harga = $total;
    //     $cart->save();
     
    //      // Generate unique order_id
    //      $order_id = 'CART-' . uniqid();
     
    //      try {
    //          $charge = null;
    //          $paymentUrl = null;
     
    //          if ($validated['payment_method'] === 'qris') {
    //              $params = [
    //                  'payment_type' => 'qris',
    //                  'transaction_details' => [
    //                      'order_id' => $order_id,
    //                      'gross_amount' => $cart->total_harga,
    //                  ],
    //                  'customer_details' => [
    //                      'first_name' => $cart->user->name,
    //                      'email' => $cart->user->email,
    //                  ]
    //              ];
     
    //              $charge = \Midtrans\CoreApi::charge($params);
     
    //              foreach ($charge->actions ?? [] as $action) {
    //                  if ($action->name === 'generate-qr-code') {
    //                      $paymentUrl = $action->url;
    //                      break;
    //                  }
    //              }
    //          } elseif ($validated['payment_method'] === 'link') {
    //              // Use Snap API for 'link'
    //              $params = [
    //                  'transaction_details' => [
    //                      'order_id' => $order_id,
    //                      'gross_amount' => $cart->total_harga,
    //                  ],
    //                  'customer_details' => [
    //                      'first_name' => $cart->user->name,
    //                      'email' => $cart->user->email,
    //                  ]
    //              ];
     
    //              $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
    //              $paymentUrl = $snapUrl;
     
    //              $charge = (object)[
    //                  'transaction_id' => uniqid('txn_'),
    //                  'transaction_status' => 'pending',
    //              ];
    //          }

    //          if ($validated['payment_method'] === 'cod') {
    //              \App\Models\PaymentGateway::create([
    //                  'transaction_id' => $order_id,
    //                  'order_id' => $order_id,
    //                  'user_id' => $cart->user_id ? $cart->user_id : $cart->guest_id,
    //                  'cart_id' => $cart->id,
    //                  'payment_method' => $validated['payment_method'],
    //                  'payment_status' => "settlement",
    //                  'payment_date' => now(),
    //                  'amount' => $cart->total_harga,
    //              ]);

    //             $cart->status_barang = 'akan dikirim';
    //             $cart->sudah_bayar = true;
    //             $cart->save();

                 
    //             return response()->json([
    //                 'message' => 'Payment initiated',
    //                 'status' => 'cod',
    //                 'order_id' => $order_id,
    //             ], 201);
    //          }
     
    //          \App\Models\PaymentGateway::create([
    //              'transaction_id' => $charge->transaction_id,
    //              'order_id' => $order_id,
    //              'user_id' => $cart->user_id,
    //              'cart_id' => $cart->id,
    //              'payment_method' => $validated['payment_method'],
    //              'payment_status' => $charge->transaction_status,
    //              'payment_date' => now(),
    //              'amount' => $cart->total_harga,
    //          ]);
     
    //          $cart->status = 'Menunggu pembayaran';
    //          $cart->save();
     
    //          return response()->json([
    //              'message' => 'Payment initiated',
    //              'status' => $charge->transaction_status ?? 'pending',
    //              'order_id' => $order_id,
    //              'payment_url' => $paymentUrl,
    //          ], 201);
     
    //      } catch (\Exception $e) {
    //          \Log::error('Cart payment error: ' . $e->getMessage());
    //          return response()->json([
    //              'error' => 'Failed to process payment',
    //              'details' => $e->getMessage()
    //          ], 500);
    //      }
    //  }
     

    // public function topUp(Request $request)
    // {
    //     // Midtrans setup
    //     \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
    //     \Midtrans\Config::$isProduction = false;
    //     \Midtrans\Config::$isSanitized = true;
    //     \Midtrans\Config::$is3ds = true;

    //     // Validasi input
    //     $validated = $request->validate([
    //         'user_id' => 'required|exists:users,id',
    //         'payment_method' => 'required|string|in:qris,link',
    //         'amount' => 'required|numeric|min:1000',
    //     ]);

    //     // Ambil data user
    //     $user = \App\Models\User::find($validated['user_id']);

    //     // Periksa apakah user bertipe "pengguna"
    //     if ($user->tipe !== 'pengguna') {
    //         return response()->json([
    //             'error' => 'Top-up hanya tersedia untuk akun bertipe pengguna'
    //         ], 403);
    //     }

    //     // Generate unique order_id
    //     $order_id = 'TOPUP-' . uniqid();

    //     try {
    //         $charge = null;
    //         $paymentUrl = null;

    //         if ($validated['payment_method'] === 'qris') {
    //             // Gunakan Core API untuk QRIS
    //             $params = [
    //                 'payment_type' => 'qris',
    //                 'transaction_details' => [
    //                     'order_id' => $order_id,
    //                     'gross_amount' => $validated['amount'],
    //                 ],
    //                 'customer_details' => [
    //                     'first_name' => explode(' ', $user->fullname)[0],
    //                     'last_name' => explode(' ', $user->fullname)[1] ?? '',
    //                     'email' => $user->email,
    //                 ]
    //             ];

    //             $charge = \Midtrans\CoreApi::charge($params);

    //             // Ambil URL QR dari actions
    //             foreach ($charge->actions ?? [] as $action) {
    //                 if ($action->name === 'generate-qr-code') {
    //                     $paymentUrl = $action->url;
    //                     break;
    //                 }
    //             }
    //         } else {
    //             // Gunakan Snap API untuk metode "link"
    //             $params = [
    //                 'transaction_details' => [
    //                     'order_id' => $order_id,
    //                     'gross_amount' => $validated['amount'],
    //                 ],
    //                 'customer_details' => [
    //                     'first_name' => explode(' ', $user->fullname)[0],
    //                     'last_name' => explode(' ', $user->fullname)[1] ?? '',
    //                     'email' => $user->email,
    //                 ],
    //             ];

    //             $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
    //             $paymentUrl = $snapUrl;

    //             $charge = (object)[
    //                 'transaction_id' => uniqid('txn_'),
    //                 'transaction_status' => 'pending',
    //             ];
    //         }

    //         // Simpan data pembayaran ke database
    //         \App\Models\PaymentGateway::create([
    //             'transaction_id' => $charge->transaction_id,
    //             'order_id' => $order_id,
    //             'user_id' => $user->id,
    //             'payment_method' => $validated['payment_method'],
    //             'payment_status' => $charge->transaction_status,
    //             'payment_date' => now(),
    //             'amount' => $validated['amount'],
    //         ]);

    //         // Tambahkan saldo jika pembayaran berhasil
    //         if (in_array($charge->transaction_status, ['settlement', 'capture'])) {
    //             $user->saldo += $validated['amount'];
    //             $user->save();
    //         }

    //         return response()->json([
    //             'message' => 'Top-up initiated',
    //             'status' => $charge->transaction_status ?? 'pending',
    //             'order_id' => $order_id,
    //             'payment_url' => $paymentUrl,
    //         ], 201);

    //     } catch (\Exception $e) {
    //         \Log::error('Top-up error: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to process top-up',
    //             'details' => $e->getMessage()
    //         ], 500);
    //     }
    // }



    // /**
    //  * @OA\Get(
    //  *     path="/api/payment/transactions",
    //  *     summary="Get list of transactions",
    //  *     description="Fetches all transactions. Admin sees all transactions, while users see only their own.",
    //  *     tags={"Payment Gateway"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Transaction list fetched successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="message", type="string", example="Transaction list fetched successfully"),
    //  *             @OA\Property(
    //  *                 property="transactions",
    //  *                 type="array",
    //  *                 @OA\Items(
    //  *                     @OA\Property(property="id", type="integer", example=1),
    //  *                     @OA\Property(property="transaction_id", type="string", example="txn_123456789"),
    //  *                     @OA\Property(property="order_id", type="string", example="CART-123456"),
    //  *                     @OA\Property(property="user_id", type="integer", example=1),
    //  *                     @OA\Property(property="cart_id", type="integer", example=2),
    //  *                     @OA\Property(property="payment_method", type="string", example="qris"),
    //  *                     @OA\Property(property="payment_status", type="string", example="pending"),
    //  *                     @OA\Property(property="payment_date", type="string", format="date-time", example="2025-05-20T12:00:00Z"),
    //  *                     @OA\Property(property="amount", type="number", format="float", example=50000),
    //  *                     @OA\Property(
    //  *                         property="cart",
    //  *                         type="object",
    //  *                         @OA\Property(property="id", type="integer", example=2),
    //  *                         @OA\Property(
    //  *                             property="user",
    //  *                             type="object",
    //  *                             @OA\Property(property="id", type="integer", example=1),
    //  *                             @OA\Property(property="name", type="string", example="John Doe"),
    //  *                             @OA\Property(property="email", type="string", example="john@example.com")
    //  *                         )
    //  *                     )
    //  *                 )
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=403,
    //  *         description="Unauthorized",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="error", type="string", example="Unauthorized")
    //  *         )
    //  *     )
    //  * )
    //  */
    // public function getTransactions(Request $request)
    // {
    //     // Gate::authorize('getTransactions', PaymentGateway::class);
    //     $user = $request->user();

    //     if ($user->tipe !== 'admin') {
    //         return response()->json([
    //             'message' => 'Unauthorized',
    //             'error' => 'You do not have permission to view all transactions'
    //         ], 403);
    //     }

    //     $transactions = \App\Models\PaymentGateway::with(['cart.user'])->latest()->get();
    //     $delivery = Cart::where('sudah_bayar', 1)->get();

    //     return response()->json([
    //         'message' => 'Transaction list fetched successfully',
    //         'transactions' => $transactions,
    //         'delivery' => $delivery
    //     ]);
    // }
}
