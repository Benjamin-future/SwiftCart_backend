<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Wallet;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * List all registered vendors, with owner details, wallet
     * balance, and total sales — for the admin "Registered Vendors"
     * screen. Admin-only (route sits behind the 'admin' middleware).
     */
    public function index()
    {
        $profiles = VendorProfile::with('user:id,name,email')->get();

        $data = $profiles->map(function (VendorProfile $profile) {
            return $this->presentVendor($profile);
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(VendorProfile $vendor)
    {
        $vendor->load('user:id,name,email');

        return response()->json([
            'data' => $this->presentVendor($vendor),
        ]);
    }

    /**
     * Full edit (the "Save" button in the details modal).
     * Only touches business-profile fields — NOT status, which has
     * its own dedicated endpoint below, and NOT the user's role.
     */
    public function update(Request $request, VendorProfile $vendor)
    {
        $validated = $request->validate([
            'business_name' => 'sometimes|required|string|max:255',
            'phone'         => 'sometimes|nullable|string|max:20',
            'address'       => 'sometimes|nullable|string|max:255',
        ]);

        $vendor->update($validated);

        return response()->json([
            'message' => 'Vendor updated successfully.',
            'data'    => $this->presentVendor($vendor->fresh('user')),
        ]);
    }

    /**
     * Activate / deactivate a vendor. This is what the frontend's
     * "toggleVendorStatus" (the 🚫/✅ button) actually calls — it does
     * NOT delete anything despite the button's CSS class name.
     */
    public function updateStatus(Request $request, VendorProfile $vendor)
    {
        $validated = $request->validate([
            'status' => 'required|in:Active,Inactive',
        ]);

        $vendor->status = $validated['status'];
        $vendor->save();

        return response()->json([
            'message' => "Vendor marked {$validated['status']}.",
            'data'    => $this->presentVendor($vendor->fresh('user')),
        ]);
    }

    public function destroy(VendorProfile $vendor)
    {
        $vendor->delete();

        return response()->json([
            'message' => 'Vendor profile deleted.',
        ]);
    }

    /* ----------------- helpers ----------------- */

    /**
     * Shape a VendorProfile + its User + wallet + sales into exactly
     * what the frontend table/modal expects: business_name,
     * owner_name, email, phone, address, status, wallet_balance,
     * total_sales.
     */
    private function presentVendor(VendorProfile $profile): array
    {
        $wallet = Wallet::where('user_id', $profile->user_id)->first();

        $totalSales = 0;

        // Wrapped defensively — depends on your real OrderItem schema
        // (assumed: vendor_id, price, quantity, status). Returns 0
        // rather than a 500 if any of those columns don't match.
        if (class_exists(\App\Models\OrderItem::class)) {
            try {
                $orderItem = \App\Models\OrderItem::class;

                $totalSales = $orderItem::where('vendor_id', $profile->user_id)
                    ->where('status', 'completed')
                    ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
                    ->value('total');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'id'             => $profile->id,
            'user_id'        => $profile->user_id,
            'business_name'  => $profile->business_name,
            'owner_name'     => $profile->user->name ?? null,
            'email'          => $profile->user->email ?? null,
            'phone'          => $profile->phone,
            'address'        => $profile->address,
            'status'         => $profile->status,
            'wallet_balance' => (float) ($wallet->balance ?? 0),
            'total_sales'    => (float) $totalSales,
        ];
    }
}