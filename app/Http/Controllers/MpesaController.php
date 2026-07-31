<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MpesaController extends Controller
{
    protected MpesaService $mpesa;

    public function __construct(MpesaService $mpesa)
    {
        $this->mpesa = $mpesa;
    }

    /*
    |--------------------------------------------------------------------------
    | Initiate STK Push
    |--------------------------------------------------------------------------
    */

    public function stkPush(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount'   => 'required|numeric|min:1',
            'phone'    => 'required|string',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        $user = $request->user();

        $isAdmin =
            ($user->role === 'admin') ||
            ($user->is_super_admin ?? false);

        if (!$isAdmin && $order->user_id != $user->id) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $phone = $this->normalizePhone($validated['phone']);

        try {

            $response = $this->mpesa->stkPush([
                'order_id'    => $order->id,
                'phone'       => $phone,
                'amount'      => (int) round($validated['amount']),
                'reference'   => 'ORDER-' . $order->id,
                'description' => 'SwiftCart Payment',
            ]);

        } catch (\Throwable $e) {

            Log::error('STK PUSH ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Unable to contact Safaricom.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        $accepted = ($response['ResponseCode'] ?? null) === '0';

        $payment = Payment::create([
            'order_id'            => $order->id,
            'amount'              => (int) round($validated['amount']),
            'phone'               => $phone,
            'status'              => $accepted ? 'pending' : 'failed',
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            'result_code'         => $response['ResponseCode'] ?? null,
            'result_desc'         => $response['ResponseDescription']
                ?? $response['errorMessage']
                ?? null,
        ]);

        if (!$accepted) {

            return response()->json([
                'status'  => 'failed',
                'message' => $response['errorMessage']
                    ?? $response['ResponseDescription']
                    ?? 'STK Push failed.',
                'payment' => $payment,
            ], 422);
        }

        return response()->json([
            'status'             => 'pending',
            'payment_id'         => $payment->id,
            'merchantRequestID'  => $payment->merchant_request_id,
            'checkoutRequestID'  => $payment->checkout_request_id,
            'ResponseCode'       => $response['ResponseCode'],
            'CustomerMessage'    => $response['CustomerMessage']
                ?? 'Check your phone to complete payment.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Safaricom Callback
    |--------------------------------------------------------------------------
    */

    public function callback(Request $request)
    {
        $payload = $request->json()->all();

        Log::info('M-PESA CALLBACK', $payload);

        $stk = data_get($payload, 'Body.stkCallback', []);

        $checkoutRequestID = data_get($stk, 'CheckoutRequestID');
        $merchantRequestID = data_get($stk, 'MerchantRequestID');
        $resultCode        = (string) data_get($stk, 'ResultCode');
        $resultDesc        = data_get($stk, 'ResultDesc');

        $payment = Payment::where('checkout_request_id', $checkoutRequestID)
            ->orWhere('merchant_request_id', $merchantRequestID)
            ->latest()
            ->first();

        if (!$payment) {

            Log::warning('Payment not found for checkout ID: ' . $checkoutRequestID);

            return response()->json([
                'success' => true
            ]);
        }

        $payment->result_code  = $resultCode;
        $payment->result_desc  = $resultDesc;
        $payment->raw_callback = $payload;

        if ($resultCode === '0') {

            $metadata = collect(
                data_get($stk, 'CallbackMetadata.Item', [])
            )->keyBy('Name');

            $payment->status = 'success';

            $payment->amount = data_get(
                $metadata->get('Amount'),
                'Value',
                $payment->amount
            );

            $payment->phone = data_get(
                $metadata->get('PhoneNumber'),
                'Value',
                $payment->phone
            );

            $payment->mpesa_receipt = data_get(
                $metadata->get('MpesaReceiptNumber'),
                'Value'
            );

            // Execute Order Updates and Vendor/Admin Wallet Credits inside a DB Transaction
            if ($payment->order) {
                DB::transaction(function () use ($payment) {
                    $order = $payment->order;

                    // 1. Update Order Status
                    $order->update([
                        'payment_status' => 'paid',
                        'status'         => 'accepted',
                    ]);

                    // 2. Sync OrderItems Status
                    $order->items()->update([
                        'status' => 'accepted'
                    ]);

                    // Find Admin User & Wallet for Commission Credit
                    $adminUser = User::where('role', 'admin')
                        ->orWhere('is_super_admin', true)
                        ->first();

                    $adminWallet = $adminUser
                        ? Wallet::firstOrCreate(['user_id' => $adminUser->id], ['balance' => 0])
                        : null;

                    // 3. Credit Vendor & Admin Wallets
                    foreach ($order->items as $item) {
                        $vendorId = $item->vendor_id;

                        if ($vendorId) {
                            $itemTotal       = $item->price * $item->quantity;
                            $vendorEarnings  = $itemTotal * 0.95; // 95% payout to vendor
                            $adminCommission = $itemTotal * 0.05; // 5% commission to admin

                            // --- Credit Vendor Wallet ---
                            $vendorWallet = Wallet::firstOrCreate(
                                ['user_id' => $vendorId],
                                ['balance' => 0]
                            );

                            $vendorWallet->increment('balance', $vendorEarnings);

                            WalletTransaction::create([
                                'wallet_id'   => $vendorWallet->id,
                                'user_id'     => $vendorId,
                                'type'        => 'Earnings',
                                'amount'      => $vendorEarnings,
                                'status'      => 'Completed',
                                'description' => "Earning from Order #{$order->id} - {$item->product_name}"
                            ]);

                            // --- Credit Admin Wallet (Marketplace Commission) ---
                            if ($adminWallet && $adminUser) {
                                $adminWallet->increment('balance', $adminCommission);

                                WalletTransaction::create([
                                    'wallet_id'   => $adminWallet->id,
                                    'user_id'     => $adminUser->id,
                                    'type'        => 'Commission',
                                    'amount'      => $adminCommission,
                                    'status'      => 'Completed',
                                    'description' => "5% Commission from Order #{$order->id} - {$item->product_name}"
                                ]);
                            }
                        }
                    }
                });
            }

        } else {

            $payment->status = 'failed';

            if ($payment->order) {
                $payment->order->update([
                    'payment_status' => 'failed',
                ]);
            }
        }

        $payment->save();

        return response()->json([
            'success' => true
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Status
    |--------------------------------------------------------------------------
    */

    public function status(Request $request, Order $order)
    {
        $user = $request->user();

        $isAdmin =
            ($user->role === 'admin') ||
            ($user->is_super_admin ?? false);

        if (!$isAdmin && $order->user_id != $user->id) {

            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $payment = $order->payments()->latest()->first();

        return response()->json([
            'status'  => $payment?->status ?? 'none',
            'receipt' => $payment?->mpesa_receipt,
            'desc'    => $payment?->result_desc,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Manual STK Query
    |--------------------------------------------------------------------------
    */

    public function query(Request $request)
    {
        $validated = $request->validate([
            'checkoutRequestID' => 'required|string'
        ]);

        try {

            return response()->json(
                $this->mpesa->stkQuery(
                    $validated['checkoutRequestID']
                )
            );

        } catch (\Throwable $e) {

            Log::error($e->getMessage());

            return response()->json([
                'message' => 'Unable to query payment.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Phone Formatter
    |--------------------------------------------------------------------------
    */

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (Str::startsWith($phone, '0')) {
            return '254' . substr($phone, 1);
        }

        if (Str::startsWith($phone, '7')) {
            return '254' . $phone;
        }

        if (Str::startsWith($phone, '254')) {
            return $phone;
        }

        return $phone;
    }
}