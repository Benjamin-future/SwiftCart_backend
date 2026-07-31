<?php

namespace App\Http\Controllers;

use App\Models\VendorRequest;
use Illuminate\Http\Request;

class VendorRequestController extends Controller
{
    /* =========================================================
     |  SELF endpoints (any authenticated user)
     |  Routes:
     |   POST /api/vendor/request
     |   GET  /api/vendor/request/me
     * ========================================================*/

    /**
     * Submit a new vendor application. Sent as multipart/form-data
     * because of the optional permit file upload, so all fields
     * arrive as strings — that's fine, Laravel's validator handles it.
     *
     * Field names below match the actual "Become a Vendor" form:
     *   business_name, business_email, phone, mpesa (not
     *   mpesa_number!), address, id_number, kra_pin, description,
     *   permit (file, optional).
     */
    public function submit(Request $request)
    {
        $data = $request->validate([
            'business_name'  => 'required|string|max:255',
            'business_email' => 'required|email|max:255',
            'phone'          => 'required|string|max:20',
            'mpesa'          => 'required|string|max:20',
            'address'        => 'required|string|max:255',
            'id_number'      => 'required|string|max:20',
            'kra_pin'        => 'required|string|max:20',
            'description'    => 'required|string',
            'permit'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
        ]);

        $userId = $request->user()->id;

        $existing = VendorRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a pending vendor request.',
                'request' => $existing,
            ], 422);
        }

        $permitPath = null;

        if ($request->hasFile('permit')) {
            // Requires `php artisan storage:link` to have been run
            // once, so files under storage/app/public are reachable
            // at /storage/... via the public disk symlink.
            $permitPath = $request->file('permit')->store('vendor-permits', 'public');
        }

        $vendorRequest = VendorRequest::create([
            'user_id'         => $userId,
            'business_name'   => $data['business_name'],
            'business_email'  => $data['business_email'],
            'phone'           => $data['phone'],
            'mpesa_number'    => $data['mpesa'],
            'address'         => $data['address'],
            'id_number'       => $data['id_number'],
            'kra_pin'         => $data['kra_pin'],
            'description'     => $data['description'],
            'permit_path'     => $permitPath,
            'status'          => 'pending',
        ]);

        return response()->json([
            'message' => 'Vendor request submitted successfully.',
            'request' => $vendorRequest,
        ], 201);
    }

    /**
     * Let a user check the status of their own most recent request.
     */
    public function me(Request $request)
    {
        $vendorRequest = VendorRequest::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$vendorRequest) {
            return response()->json([
                'message' => 'No vendor request found.',
            ], 404);
        }

        return response()->json([
            'request' => $vendorRequest,
        ]);
    }

    /* =========================================================
     |  ADMIN endpoints (behind 'admin' middleware)
     |  Routes:
     |   GET    /api/admin/vendor-requests
     |   GET    /api/admin/vendor-requests/{vendorRequest}
     |   POST   /api/admin/vendor-requests/{vendorRequest}/approve
     |   POST   /api/admin/vendor-requests/{vendorRequest}/reject
     |   DELETE /api/admin/vendor-requests/{vendorRequest}
     * ========================================================*/

    public function index()
    {
        $requests = VendorRequest::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function show(VendorRequest $vendorRequest)
    {
        $vendorRequest->load('user:id,name,email');

        return response()->json([
            'data' => $vendorRequest,
        ]);
    }

    /**
     * Approve a vendor request, promote the applicant's account, and
     * create (or update, if re-approving) their VendorProfile — this
     * is what makes them show up on the admin "Registered Vendors"
     * screen.
     */
    public function approve(VendorRequest $vendorRequest)
    {
        if ($vendorRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $vendorRequest->status = 'approved';
        $vendorRequest->save();

        $vendorRequest->user()->update(['role' => 'vendor']);

        \App\Models\VendorProfile::updateOrCreate(
            ['user_id' => $vendorRequest->user_id],
            [
                'business_name' => $vendorRequest->business_name,
                'phone'         => $vendorRequest->phone,
                'address'       => $vendorRequest->address,
                'status'        => 'Active',
            ]
        );

        return response()->json([
            'message' => 'Vendor request approved.',
            'data'    => $vendorRequest->fresh('user'),
        ]);
    }

    public function reject(VendorRequest $vendorRequest)
    {
        if ($vendorRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $vendorRequest->status = 'rejected';
        $vendorRequest->save();

        return response()->json([
            'message' => 'Vendor request rejected.',
            'data'    => $vendorRequest,
        ]);
    }

    public function destroy(VendorRequest $vendorRequest)
    {
        $vendorRequest->delete();

        return response()->json([
            'message' => 'Vendor request deleted.',
        ]);
    }
}