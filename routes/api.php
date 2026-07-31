<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorRequestController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\RiderController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/products',           [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);

/* M-Pesa Callback */
Route::post('/payments/mpesa/callback', [MpesaController::class, 'callback']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ---------------- Session ----------------
    Route::get('/me',   [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ---------------- Become a Vendor ----------------
    Route::post('/vendor/request', [VendorRequestController::class, 'submit']);
    Route::get('/vendor/request/me', [VendorRequestController::class, 'me']);

    // ---------------- Orders ----------------
    Route::post('/orders',           [OrderController::class, 'store']);
    Route::get('/my-orders',         [OrderController::class, 'userOrders']);
    Route::get('/orders',            [OrderController::class, 'index']);
    Route::get('/orders/{order}',    [OrderController::class, 'show']);

    // ---------------- Budget ----------------
    Route::get('/budget',                        [BudgetController::class, 'show']);
    Route::post('/budget',                       [BudgetController::class, 'store']);
    Route::match(['put','patch'], '/budget',     [BudgetController::class, 'update']);

    // ---------------- Wallet (Buyer Store Credit) ----------------
    Route::get('/wallet',                        [WalletController::class, 'showSelf']);
    Route::post('/wallet',                       [WalletController::class, 'storeSelf']);
    Route::match(['put','patch'], '/wallet',     [WalletController::class, 'updateSelf']);
    Route::delete('/wallet',                     [WalletController::class, 'destroySelf']);

    // ---------------- Deliveries ----------------
    Route::post('/deliveries',                          [DeliveryController::class, 'store']);
    Route::get('/deliveries',                           [DeliveryController::class, 'index']);
    Route::get('/deliveries/{delivery}',                [DeliveryController::class, 'show']);
    Route::get('/orders/{order}/delivery',              [DeliveryController::class, 'showByOrder']);
    Route::get('/orders/{order}/deliveries',            [DeliveryController::class, 'history']);
    Route::match(['put','patch'], '/deliveries/{delivery}',  [DeliveryController::class, 'update']);
    Route::post('/deliveries/{delivery}/cancel',              [DeliveryController::class, 'cancel']);
    Route::post('/orders/{order}/delivery/reschedule',        [DeliveryController::class, 'reschedule']);
    Route::post('/orders/{order}/delivery/received',          [DeliveryController::class, 'markDelivered']);
    Route::get('/deliveries/{delivery}/track',          [DeliveryController::class, 'track']);

    // ---------------- M-PESA ----------------
    Route::post('/payments/mpesa/stk-push',             [MpesaController::class, 'stkPush']);
    Route::get('/payments/mpesa/status/{order}',        [MpesaController::class, 'status']);
    Route::match(['get','post'], '/payments/mpesa/query', [MpesaController::class, 'query']);

    /*
    |--------------------------------------------------------------------------
    | Vendor-only
    |--------------------------------------------------------------------------
    */
    Route::middleware('vendor')->prefix('vendor')->group(function () {

        Route::get('/products',                          [ProductController::class, 'vendorIndex']);
        Route::post('/products',                          [ProductController::class, 'store']);
        Route::match(['put','patch'], '/products/{product}', [ProductController::class, 'updateOwn']);
        Route::delete('/products/{product}',              [ProductController::class, 'destroyOwn']);

        Route::get('/orders',                             [OrderController::class, 'vendorOrders']);
        Route::match(['put','patch'], '/orders/{orderItem}/status', [OrderController::class, 'updateItemStatus']);

        // Vendor Wallet (Matched to Frontend expectations)
        Route::get('/wallet',                             [WalletController::class, 'vendorSummary']);
        Route::get('/wallet/transactions',                [WalletController::class, 'transactionsSelf']);
        Route::post('/wallet/withdraw',                   [WalletController::class, 'requestWithdrawal']);

        Route::get('/riders',                              [RiderController::class, 'index']);

        Route::get('/deliveries',                           [DeliveryController::class, 'index']);
        Route::post('/deliveries/{delivery}/assign',        [DeliveryController::class, 'assignRider']);

        Route::get('/profile',                            [VendorController::class, 'showProfile']);
        Route::match(['put','patch'], '/profile',         [VendorController::class, 'updateProfile']);

        Route::get('/dashboard',                          [VendorController::class, 'dashboard']);
    });

    /*
    |--------------------------------------------------------------------------
    | Rider-only
    |--------------------------------------------------------------------------
    */
    Route::middleware('rider')->prefix('rider')->group(function () {
        Route::get('/deliveries',                         [DeliveryController::class, 'riderAssigned']);
        Route::match(['put','patch'], '/deliveries/{delivery}/status', [DeliveryController::class, 'riderUpdateStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Super Admin–only
    |--------------------------------------------------------------------------
    */
    Route::middleware('superadmin')->group(function () {
        Route::post('/admin/users/{id}/promote', function ($id) {
            $target = User::findOrFail($id);
            if ($target->is_super_admin ?? false) {
                abort(400, 'Cannot change role of a Super Admin.');
            }
            $target->role = 'admin';
            $target->save();

            return response()->json([
                'message' => 'Promoted to admin',
                'user'    => [
                    'id'    => $target->id,
                    'name'  => $target->name,
                    'email' => $target->email,
                    'role'  => $target->role,
                ],
            ]);
        });

        Route::post('/admin/users/{id}/demote', function ($id) {
            $target = User::findOrFail($id);
            if ($target->is_super_admin ?? false) {
                abort(400, 'Cannot demote a Super Admin.');
            }
            $target->role = 'user';
            $target->save();

            return response()->json([
                'message' => 'Demoted to user',
                'user'    => [
                    'id'    => $target->id,
                    'name'  => $target->name,
                    'email' => $target->email,
                    'role'  => $target->role,
                ],
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin-only
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {

        Route::get('/users',                              [UserController::class, 'index']);
        Route::post('/users',                              [UserController::class, 'store']);
        Route::match(['put','patch'], '/users/{user}',    [UserController::class, 'update']);
        Route::delete('/users/{user}',                    [UserController::class, 'destroy']);
        Route::get('/admin/users',                        [UserController::class, 'index']);

        Route::post('/admin/users/{user}/verify',         [UserController::class, 'verify']);
        Route::post('/admin/users/{user}/unverify',       [UserController::class, 'unverify']);

        Route::get('/vendor/requests',                              [VendorRequestController::class, 'index']);
        Route::get('/vendor/requests/{vendorRequest}',              [VendorRequestController::class, 'show']);
        Route::post('/vendor/requests/{vendorRequest}/approve',     [VendorRequestController::class, 'approve']);
        Route::post('/vendor/requests/{vendorRequest}/reject',      [VendorRequestController::class, 'reject']);

        Route::get('/admin/vendor-requests',                              [VendorRequestController::class, 'index']);
        Route::get('/admin/vendor-requests/{vendorRequest}',              [VendorRequestController::class, 'show']);
        Route::post('/admin/vendor-requests/{vendorRequest}/approve',     [VendorRequestController::class, 'approve']);
        Route::post('/admin/vendor-requests/{vendorRequest}/reject',      [VendorRequestController::class, 'reject']);
        Route::delete('/admin/vendor-requests/{vendorRequest}',           [VendorRequestController::class, 'destroy']);

        Route::get('/admin/vendors',                          [VendorController::class, 'index']);
        Route::get('/admin/vendors/{vendor}',                 [VendorController::class, 'show']);
        Route::match(['put','patch'], '/admin/vendors/{vendor}', [VendorController::class, 'update']);
        Route::patch('/admin/vendors/{vendor}/status',        [VendorController::class, 'updateStatus']);
        Route::delete('/admin/vendors/{vendor}',               [VendorController::class, 'destroy']);

        Route::post('/products',                          [ProductController::class, 'store']);
        Route::match(['put','patch'], '/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}',              [ProductController::class, 'destroy']);

        Route::post('/categories',                        [CategoryController::class, 'store']);
        Route::match(['put','patch'], '/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}',           [CategoryController::class, 'destroy']);

        Route::match(['put','patch'], '/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::delete('/orders/{order}',                  [OrderController::class, 'destroy']);
        Route::delete('/orders',                          [OrderController::class, 'destroyAll']);

        Route::get('/wallets',                            [WalletController::class, 'index']);
        Route::get('/users/{user}/wallet',                [WalletController::class, 'show']);
        Route::post('/users/{user}/wallet',               [WalletController::class, 'store']);
        Route::match(['put','patch'], '/users/{user}/wallet', [WalletController::class, 'update']);
        Route::delete('/users/{user}/wallet',             [WalletController::class, 'destroy']);

        // Admin Wallet Endpoints
        Route::get('/admin/wallet/summary',               [WalletController::class, 'adminSummary']);
        Route::post('/admin/wallet/withdraw',              [WalletController::class, 'adminWithdraw']);
        Route::get('/admin/wallet/report',                 [WalletController::class, 'adminReport']);

        Route::get('/admin/deliveries',                     [DeliveryController::class, 'index']);
        Route::post('/admin/deliveries/{delivery}/approve', [DeliveryController::class, 'adminApprove']);
        Route::post('/admin/deliveries/{delivery}/status',  [DeliveryController::class, 'adminSetStatus']);
        Route::post('/admin/deliveries/{delivery}/assign',  [DeliveryController::class, 'assignRider']);

        Route::get('/riders',                             [RiderController::class, 'index']);
        Route::post('/riders',                            [RiderController::class, 'store']);
        Route::get('/riders/{rider}',                     [RiderController::class, 'show']);
        Route::match(['put','patch'], '/riders/{rider}', [RiderController::class, 'update']);
        Route::delete('/riders/{rider}',                  [RiderController::class, 'destroy']);
    });
});