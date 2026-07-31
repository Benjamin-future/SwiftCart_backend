<?php

namespace App\Http\Controllers;

use App\Models\Rider;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    /**
     * List all riders.
     * Used by BOTH:
     *  - Admin, via GET /riders (full management screen)
     *  - Vendor, via GET /vendor/riders (read-only, for assigning
     *    deliveries — same method, different route/middleware)
     */
    public function index()
    {
        $riders = Rider::orderBy('name')->get();

        return response()->json([
            'data' => $riders,
        ]);
    }

    /**
     * Register a new rider (admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255|unique:riders,email',
            'phone'   => 'required|string|max:20',
            'vehicle' => 'nullable|in:Motorbike,Bicycle,Car',
            'status'  => 'nullable|in:Available,Busy,Offline',
        ]);

        $rider = Rider::create($validated);

        return response()->json([
            'message' => 'Rider registered successfully.',
            'data'    => $rider,
        ], 201);
    }

    /**
     * Show a single rider (admin only).
     */
    public function show(Rider $rider)
    {
        return response()->json([
            'data' => $rider,
        ]);
    }

    /**
     * Update a rider's details or status (admin only).
     * Also used by the "activate" button on the frontend, which PATCHes
     * just { status: 'Available' }.
     */
    public function update(Request $request, Rider $rider)
    {
        $validated = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'email'   => 'nullable|email|max:255|unique:riders,email,' . $rider->id,
            'phone'   => 'sometimes|required|string|max:20',
            'vehicle' => 'sometimes|in:Motorbike,Bicycle,Car',
            'status'  => 'sometimes|in:Available,Busy,Offline',
        ]);

        $rider->update($validated);

        return response()->json([
            'message' => 'Rider updated successfully.',
            'data'    => $rider->fresh(),
        ]);
    }

    /**
     * Delete a rider (admin only).
     */
    public function destroy(Rider $rider)
    {
        $rider->delete();

        return response()->json([
            'message' => 'Rider deleted successfully.',
        ]);
    }
}