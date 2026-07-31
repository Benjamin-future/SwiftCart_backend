<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    private const STATUSES = [
        'requested', 'approved', 'assigned', 'picked_up',
        'in_transit', 'delivered', 'cancelled',
    ];

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user?->role === 'admin' || $user?->is_super_admin === true;
    }

    /**
     * Does this delivery's order contain at least one product
     * belonging to the given vendor? Used to gate vendor access.
     */
    private function vendorOwnsDelivery(Delivery $delivery, int $vendorId): bool
    {
        return $delivery->order()
            ->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId))
            ->exists();
    }

    /* =========================================================
     |  SHARED (customer + admin + vendor, scoped per role)
     * ========================================================*/

    /**
     * GET /deliveries        (customer: own, admin: all)
     * GET /admin/deliveries  (admin: all)
     * GET /vendor/deliveries (vendor: only deliveries for orders
     *                         containing at least one of their products)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Delivery::with(['order.user:id,name,email', 'rider']);

        if ($this->isAdmin($request)) {
            // no extra scoping — admin sees everything
        } elseif ($user->role === 'vendor') {
            $query->whereHas('order.items', fn ($q) => $q->where('vendor_id', $user->id));
        } else {
            $query->whereHas('order', fn ($q) => $q->where('user_id', $user->id));
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * POST /deliveries
     * Request a delivery for an order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id'      => 'required|exists:orders,id',
            'address'       => 'nullable|string|max:500',
            'notes'         => 'nullable|string|max:1000',
            'contact_name'  => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'scheduled_at'  => 'nullable|date',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->user_id !== $request->user()->id && !$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $delivery = Delivery::updateOrCreate(
            ['order_id' => $order->id],
            [
                'status'       => 'requested',
                'address'      => $validated['address'] ?? $order->shipping_address ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'phone'        => $validated['phone'] ?? null,
                'notes'        => $validated['notes'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Delivery requested.',
            'data'    => $delivery->load('order', 'rider'),
        ], 201);
    }

    /**
     * GET /orders/{order}/deliveries
     */
    public function history(Order $order)
    {
        $delivery = Delivery::where('order_id', $order->id)->first();

        return response()->json([
            'data' => $delivery ? [$delivery] : [],
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string|max:1000',
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:20',
        ]);

        $delivery->update($validated);

        return response()->json(['message' => 'Delivery updated.', 'data' => $delivery->fresh()]);
    }

    public function cancel(Request $request, Delivery $delivery)
    {
        $user = $request->user();

        if ($delivery->order->user_id !== $user->id && !$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $delivery->status = 'cancelled';
        $delivery->save();

        if ($delivery->rider) {
            $delivery->rider->update(['status' => 'Available']);
        }

        return response()->json(['message' => 'Delivery cancelled.', 'data' => $delivery->fresh()]);
    }

    /**
     * POST /orders/{order}/delivery/reschedule
     * Creates or updates a delivery request for an order.
     */
    public function reschedule(Request $request, Order $order)
    {
        // Check ownership or admin rights
        if ($order->user_id !== $request->user()->id && !$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'scheduled_at'  => 'required|date',
            'address'       => 'nullable|string|max:500',
            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'note'          => 'nullable|string|max:1000',
        ]);

        // Creates a new delivery if one doesn't exist, or updates existing one
        $delivery = Delivery::updateOrCreate(
            ['order_id' => $order->id],
            [
                'scheduled_at' => $validated['scheduled_at'],
                'address'      => $validated['address'] ?? null,
                'contact_name' => $validated['contact_name'] ?? null,
                'phone'        => $validated['contact_phone'] ?? null,
                'notes'        => $validated['note'] ?? null,
                'status'       => 'requested',
            ]
        );

        return response()->json([
            'message' => 'Delivery requested successfully.',
            'data'    => $delivery->fresh(['order', 'rider']),
        ]);
    }

    public function markDelivered(Request $request, Order $order)
    {
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        if ($delivery->order->user_id !== $request->user()->id && !$this->isAdmin($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $delivery->status = 'delivered';
        $delivery->delivered_at = now();
        $delivery->save();

        if ($delivery->rider) {
            $delivery->rider->update(['status' => 'Available']);
        }

        return response()->json(['message' => 'Delivery marked as received.', 'data' => $delivery->fresh()]);
    }

    public function track(Request $request, Delivery $delivery)
    {
        $user = $request->user();

        if (
            !$this->isAdmin($request)
            && $delivery->order->user_id !== $user->id
            && !($user->role === 'vendor' && $this->vendorOwnsDelivery($delivery, $user->id))
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $delivery->load('rider', 'order');

        return response()->json([
            'data' => [
                'status'          => $delivery->status,
                'address'         => $delivery->address,
                'scheduled_at'    => $delivery->scheduled_at,
                'delivered_at'    => $delivery->delivered_at,
                'rider'           => $delivery->rider ? [
                    'id'        => $delivery->rider->id,
                    'name'      => $delivery->rider->name,
                    'vehicle'   => $delivery->rider->vehicle,
                    'phone'     => $delivery->rider->phone,
                    'latitude'  => $delivery->rider->latitude,
                    'longitude' => $delivery->rider->longitude,
                ] : null,
            ],
        ]);
    }

    /* =========================================================
     |  ADMIN + VENDOR — assign a rider, update status
     * ========================================================*/

    /**
     * POST /admin/deliveries/{delivery}/approve
     */
    public function adminApprove(Delivery $delivery)
    {
        $delivery->status = 'approved';
        $delivery->save();

        return response()->json(['message' => 'Delivery approved.', 'data' => $delivery->fresh()]);
    }

    /**
     * POST /admin/deliveries/{delivery}/status
     */
    public function adminSetStatus(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        $delivery->status = $validated['status'];

        if ($validated['status'] === 'delivered') {
            $delivery->delivered_at = now();
        }

        $delivery->save();

        if (in_array($validated['status'], ['delivered', 'cancelled']) && $delivery->rider) {
            $delivery->rider->update(['status' => 'Available']);
        }

        return response()->json(['message' => 'Delivery status updated.', 'data' => $delivery->fresh('rider')]);
    }

    /**
     * POST /admin/deliveries/{delivery}/assign
     * POST /vendor/deliveries/{delivery}/assign
     */
    public function assignRider(Request $request, Delivery $delivery)
    {
        $user = $request->user();

        if (!$this->isAdmin($request) && !($user->role === 'vendor' && $this->vendorOwnsDelivery($delivery, $user->id))) {
            return response()->json(['message' => 'You cannot assign a rider to this delivery.'], 403);
        }

        $validated = $request->validate([
            'rider_id' => 'required|exists:riders,id',
        ]);

        $rider = Rider::findOrFail($validated['rider_id']);

        if ($rider->status !== 'Available') {
            return response()->json(['message' => "{$rider->name} is not currently available."], 422);
        }

        $delivery->rider_id = $rider->id;
        $delivery->assigned_by = $user->id;
        $delivery->status = 'assigned';
        $delivery->save();

        $rider->status = 'Busy';
        $rider->save();

        return response()->json([
            'message' => "Rider {$rider->name} assigned successfully.",
            'data'    => $delivery->fresh('rider'),
        ]);
    }
}