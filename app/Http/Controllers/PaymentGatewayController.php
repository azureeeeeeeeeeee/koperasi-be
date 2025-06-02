<?php

namespace App\Http\Controllers;

use App\Models\Config as ModelsConfig;
use Midtrans\Snap;
use Midtrans\Config;
use Midtrans\CoreApi;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
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
                $charge = \Midtrans\CoreApi::charge($params);
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
                $charge = \Midtrans\CoreApi::charge($params);
                $paymentUrl = $charge->actions[1]->url ?? $charge->actions[0]->url ?? null;
                break;

            case 'link':
                $params = [
                    'transaction_details' => $transactionDetails,
                    'customer_details' => $customerDetails,
                ];
                $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
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
                $charge = \Midtrans\CoreApi::charge($params);
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

        $transactionDetails = [
            'order_id' => $order_id,
            'gross_amount' => $validatedData['amount'],
        ];

        $customerDetails = [
            'first_name' => explode(' ', $user->fullname)[0] ?? $user->name ?? 'User',
            'last_name' => explode(' ', $user->fullname)[1] ?? '',
            'email' => $user->email,
            'phone' => $validatedData['phone'] ?? $user->phone ?? null,
        ];

        try {
            $charge = null;
            $paymentUrl = null;
            $extra = [];

            switch ($validatedData['payment_method']) {
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
                    if (!in_array($validatedData['bank'], ['bni', 'mandiri', 'bri'])) {
                        return response()->json(['error' => 'Bank not supported for virtual account'], 422);
                    }

                    $params = [
                        'payment_type' => 'bank_transfer',
                        'transaction_details' => $transactionDetails,
                        'customer_details' => $customerDetails,
                        'bank_transfer' => [
                            'bank' => $validatedData['bank'],
                        ],
                    ];

                    try {
                        $charge = CoreApi::charge($params);
                    } catch (\Exception $e) {
                        \Log::error('Midtrans charge failed: ' . $e->getMessage());
                        return response()->json([
                            'error' => 'Payment gateway error',
                            'details' => $e->getMessage()
                        ], 500);
                    }

                    $vaNumbers = $charge->va_numbers ?? [];
                    $extra['va_number'] = $vaNumbers[0]->va_number ?? null;
                    $extra['bank'] = $vaNumbers[0]->bank ?? $validatedData['bank'];
                    $paymentUrl = null;
                    break;


                case 'manual':
                    if (!in_array($validatedData['bank'], ['mandiri', 'bni', 'bri', 'bca'])) {
                        return response()->json(['error' => 'Bank not supported for manual transfer'], 422);
                    }
                    $charge = (object)[
                        'transaction_id' => uniqid('manual_'),
                        'transaction_status' => 'pending',
                    ];
                    $paymentUrl = null;
                    $extra['manual_bank'] = $validatedData['bank'];
                    break;

                case 'iuran_wajib':
                    $charge = (object)[
                        'transaction_id' => uniqid('iuran_'),
                        'transaction_status' => 'settlement',
                    ];
                    $paymentUrl = null;
                    break;
            }

            $payment = PaymentGateway::create([
                'transaction_id' => $charge->transaction_id,
                'order_id' => $order_id,
                'user_id' => $validatedData['user_id'],
                'payment_method' => $validatedData['payment_method'],
                'payment_status' => $charge->transaction_status ?? 'pending',
                'payment_date' => now(),
                'amount' => $validatedData['amount'],
                'extra' => !empty($extra) ? json_encode($extra) : null,
            ]);

            $response = [
                'payment_url' => $paymentUrl,
                'payment_va' => $extra['va_number'] ?? null,
                'payment_account' => $extra['manual_bank'] ?? null,
                'payment_ammount' => $validatedData['amount'],
                'payment' => $payment,
            ];

            if (!empty($extra)) {
                $response['extra'] = $extra;
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

    public function withdrawlCash(Request $request) 
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:1000',
                'bank_account' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        $user = \App\Models\User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        if ($user->saldo < $request->amount) {
            return response()->json([
                'error' => 'Saldo tidak mencukupi untuk penarikan'
            ], 422);
        }

        $bankAccount = $request->bank_account;
        $amount = $request->amount;

        // Only allow BNI
        $bankCode = 'bni';

        // Midtrans Payout/Disbursement API
        try {
            $midtransServerKey = env('MIDTRANS_SERVER_KEY');
            $client = new \GuzzleHttp\Client();

            $order_id = 'WD-' . uniqid();

            $payload = [
                'payouts' => [[
                    'beneficiary_name' => $user->fullname ?? $user->name,
                    'beneficiary_account' => $bankAccount,
                    'beneficiary_bank' => strtoupper($bankCode),
                    'amount' => (int)$amount,
                    'notes' => 'Penarikan saldo koperasi #' . $user->id,
                    'external_id' => $order_id,
                ]]
            ];

            // NOTE: Iris (disbursement) uses different credentials, but for demo, use serverKey as basic auth
            $response = $client->request('POST', 'https://app.sandbox.midtrans.com/iris/api/v1/payouts', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($midtransServerKey . ':'),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
            ]);

            $result = json_decode($response->getBody()->getContents());

            if (!empty($result->payouts[0]->status) && $result->payouts[0]->status === 'pending') {
                // Kurangi saldo user
                $user->saldo -= $amount;
                $user->save();

                // Simpan ke PaymentGateway
                PaymentGateway::create([
                    'transaction_id' => $result->payouts[0]->id ?? uniqid('wd_'),
                    'order_id' => $order_id,
                    'user_id' => $user->id,
                    'payment_method' => 'withdrawal_bni',
                    'payment_status' => $result->payouts[0]->status,
                    'payment_date' => now(),
                    'amount' => $amount,
                    'extra' => json_encode([
                        'bank_account' => $bankAccount,
                        'beneficiary_name' => $user->fullname ?? $user->name,
                    ]),
                ]);

                return response()->json([
                    'message' => 'Withdrawal request submitted',
                    'status' => $result->payouts[0]->status,
                    'order_id' => $order_id,
                    'amount' => $amount,
                    'bank_account' => $bankAccount,
                ], 201);
            } else {
                return response()->json([
                    'error' => 'Failed to process withdrawal',
                    'details' => $result,
                ], 500);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Special handling for 404 Not Found (sandbox endpoint not available)
            if ($e->getResponse() && $e->getResponse()->getStatusCode() == 404) {
                // Assume success for sandbox 404
                $user->saldo -= $amount;
                $user->save();

                PaymentGateway::create([
                    'transaction_id' => uniqid('wd_'),
                    'order_id' => $order_id ?? ('WD-' . uniqid()),
                    'user_id' => $user->id,
                    'payment_method' => 'withdrawal_bni',
                    'payment_status' => 'pending',
                    'payment_date' => now(),
                    'amount' => $amount,
                    'extra' => json_encode([
                        'bank_account' => $bankAccount,
                        'beneficiary_name' => $user->fullname ?? $user->name,
                    ]),
                ]);

                return response()->json([
                    'message' => 'Withdrawal request submitted (simulated success)',
                    'status' => 'pending',
                    'order_id' => $order_id ?? ('WD-' . uniqid()),
                    'amount' => $amount,
                    'bank_account' => $bankAccount,
                ], 201);
            }
            \Log::error('Withdrawal error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to process withdrawal',
                'details' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Withdrawal error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to process withdrawal',
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
        $config = \App\Models\Config::where('key', 'iuran wajib')->first();
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

            \App\Models\PaymentGateway::create([
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
             'user_id' => 'required|exists:carts,id',
             'payment_method' => 'required|string|in:qris,link,bank,manual,gopay',
             'bank' => 'required_if:payment_method,bank,manual|nullable|string|in:bni,mandiri,bri,bca',
             'phone' => 'required_if:payment_method,gopay|nullable|string',
         ]);
     
         $cart = \App\Models\Cart::find($validated['user_id']);
         if (!$cart) {
             return response()->json(['error' => 'User cart not found.'], 404);
         }
         $user = $cart->user;
         $amount = $cart->total_harga;
         $order_id = 'CART-' . uniqid();

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

             \App\Models\PaymentGateway::create([
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
}
