<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    private const COMMISSION_RATE = 0.05; // 5% marketplace fee

    /* =========================================================
     |  VENDOR / SELF ENDPOINTS
     |  Routes: GET /api/vendor/wallet
     * ========================================================*/

    public function vendorSummary(Request $request)
    {
        $user = $request->user();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );

        // Query transactions using wallet_id instead of user_id
        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($t) {
                return [
                    'id'          => $t->id,
                    'date'        => $t->created_at,
                    'description' => $t->description,
                    'type'        => $t->type,
                    'amount'      => $t->amount,
                    'status'      => $t->status ?? 'completed',
                ];
            });

        $pendingEarnings = 0;
        $totalOrders = 0;

        if (class_exists(OrderItem::class)) {
            try {
                $totalOrders = OrderItem::whereHas('product', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('vendor_id', $user->id);
                })->distinct('order_id')->count('order_id');

                $pendingSales = OrderItem::whereHas('product', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('vendor_id', $user->id);
                })
                ->whereHas('order', function ($q) {
                    $q->whereNotIn('status', ['delivered', 'completed', 'cancelled']);
                })
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
                ->value('total');

                $pendingEarnings = round($pendingSales * (1 - self::COMMISSION_RATE), 2);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Query total withdrawn using wallet_id
        $totalWithdrawn = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'Withdrawal')
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'balance'          => (float) $wallet->balance,
            'wallet_balance'   => (float) $wallet->balance,
            'pending_earnings' => (float) $pendingEarnings,
            'total_withdrawn'  => (float) $totalWithdrawn,
            'total_orders'     => $totalOrders,
            'transactions'     => $transactions,
        ]);
    }

    public function showSelf(Request $request)
    {
        return $this->vendorSummary($request);
    }

    /* =========================================================
     |  ORDER PAYMENT CREDIT HELPER
     |  Call this when an order status becomes 'delivered'
     * ========================================================*/

    public static function creditVendorForOrder(Order $order)
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $vendorId = $item->vendor_id ?? $item->product->vendor_id ?? $item->product->user_id ?? null;

                if (!$vendorId) continue;

                $itemTotal = $item->price * $item->quantity;
                $commission = $itemTotal * self::COMMISSION_RATE;
                $vendorEarnings = $itemTotal - $commission;

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $vendorId],
                    ['balance' => 0]
                );
                $wallet->increment('balance', $vendorEarnings);

                // Use wallet_id here
                WalletTransaction::create([
                    'wallet_id'   => $wallet->id,
                    'order_id'    => $order->id,
                    'type'        => 'Commission',
                    'amount'      => $vendorEarnings,
                    'description' => "Earnings for Order #{$order->id} (Item: " . ($item->product->name ?? 'Product') . ")",
                    'status'      => 'completed',
                ]);
            }
        });
    }

    /* =========================================================
     |  WITHDRAWAL ENDPOINTS
     * ========================================================*/

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:10',
            'phone'  => 'required|string',
        ]);

        $user = $request->user();
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        if ((float) $wallet->balance < (float) $data['amount']) {
            return response()->json(['message' => 'Insufficient balance for withdrawal.'], 422);
        }

        return DB::transaction(function () use ($wallet, $data) {
            $wallet->decrement('balance', $data['amount']);

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'type'        => 'Withdrawal',
                'amount'      => $data['amount'],
                'description' => "M-Pesa Payout to {$data['phone']}",
                'status'      => 'completed',
            ]);

            return response()->json(['message' => 'Withdrawal processed successfully.']);
        });
    }

    /* =========================================================
     |  ADMIN DASHBOARD SUMMARY
     |  GET /api/admin/wallet/summary
     * ========================================================*/

    public function adminSummary(Request $request)
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0]
        );

        $adminSales = 0;
        $pendingCommission = 0;
        $vendorCommissions = [];
        $totalOrders = 0;

        $revenueTrend = collect(range(5, 0))->map(function ($monthsAgo) {
            return [
                'label' => Carbon::now()->subMonths($monthsAgo)->format('M'),
                'value' => 0,
            ];
        })->values();

        if (class_exists(OrderItem::class)) {
            try {
                $totalOrders = Order::count();

                $adminSales = OrderItem::whereHas('product', function ($q) {
                    $q->whereHas('user', function ($u) {
                        $u->whereIn('role', ['admin', 'superadmin']);
                    });
                })
                ->whereHas('order', function ($q) {
                    $q->where('status', 'delivered');
                })
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
                ->value('total');

                $vendors = User::where('role', 'vendor')->get();

                foreach ($vendors as $v) {
                    $sales = OrderItem::whereHas('product', function ($q) use ($v) {
                        $q->where('vendor_id', $v->id)->orWhere('user_id', $v->id);
                    })
                    ->whereHas('order', function ($q) {
                        $q->where('status', 'delivered');
                    })
                    ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
                    ->value('total');

                    if ($sales > 0) {
                        $commission = round($sales * self::COMMISSION_RATE, 2);
                        $vendorCommissions[] = [
                            'id'         => $v->id,
                            'name'       => $v->name,
                            'sales'      => (float) $sales,
                            'commission' => $commission,
                            'earnings'   => round($sales - $commission, 2),
                        ];
                    }
                }

                $pendingSales = OrderItem::whereHas('order', function ($q) {
                    $q->whereNotIn('status', ['delivered', 'cancelled']);
                })->selectRaw('COALESCE(SUM(price * quantity), 0) as total')->value('total');

                $pendingCommission = round($pendingSales * self::COMMISSION_RATE, 2);

            } catch (\Throwable $e) {
                report($e);
            }
        }

        $transactions = WalletTransaction::with('wallet.user')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(function ($t) {
                return [
                    'id'          => $t->id,
                    'date'        => $t->created_at,
                    'description' => $t->description,
                    'vendor'      => $t->wallet->user->name ?? 'System',
                    'type'        => $t->type,
                    'amount'      => $t->amount,
                    'status'      => $t->status ?? 'completed',
                ];
            });

        return response()->json([
            'balance'            => (float) $wallet->balance,
            'wallet_balance'     => (float) $wallet->balance,
            'admin_sales'        => (float) $adminSales,
            'pending_commission' => (float) $pendingCommission,
            'total_withdrawals'  => (float) WalletTransaction::where('type', 'Withdrawal')->sum('amount'),
            'approved_vendors'   => User::where('role', 'vendor')->count(),
            'total_orders'       => $totalOrders,
            'vendor_commissions' => $vendorCommissions,
            'transactions'       => $transactions,
            'revenue_trend'      => $revenueTrend,
        ]);
    }
}