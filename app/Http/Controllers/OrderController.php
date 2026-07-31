<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Extended statuses to support Accept/Decline frontend UI actions
    private const STATUSES = [
        'pending',
        'accepted',
        'confirmed',
        'declined',
        'rejected',
        'processing',
        'shipped',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
    ];

    /**
     * POST /orders
     * Create a new order from a cart payload:
     *   { items: [{ product_id, quantity }, ...] }
     */
    public function store(Request $request)
    {
        // Normalize payload key aliases if sent under different names
        $rawItems = $request->input('items')
            ?? $request->input('cart')
            ?? $request->input('products')
            ?? [];

        $rawItems = collect($rawItems)->map(function ($item) {
            if (isset($item['id']) && !isset($item['product_id'])) {
                $item['product_id'] = $item['id'];
            }
            return $item;
        })->all();

        $request->merge(['items' => $rawItems]);

        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'shipping_address'   => 'nullable|string|max:500',
            'payment_method'     => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($validated, $request) {

            $total = 0;
            $lineItems = [];

            foreach ($validated['items'] as $line) {
                $product = Product::lockForUpdate()->findOrFail($line['product_id']);

                if ($product->stock < $line['quantity']) {
                    abort(422, "Insufficient stock for \"{$product->title}\". Only {$product->stock} left.");
                }

                $lineItems[] = [
                    'product'  => $product,
                    'quantity' => $line['quantity'],
                ];

                $total += $product->price * $line['quantity'];
            }

            // Fallback strings prevent NULL constraint violations when missing in request
            $shippingAddress = !empty($validated['shipping_address'])
                ? $validated['shipping_address']
                : 'Default Delivery Address';

            $paymentMethod = !empty($validated['payment_method'])
                ? $validated['payment_method']
                : 'MPESA';

            $order = Order::create([
                'user_id'          => $request->user()->id,
                'status'           => 'pending',
                'total'            => $total,
                'shipping_address' => $shippingAddress,
                'payment_method'   => $paymentMethod,
            ]);

            foreach ($lineItems as $line) {
                $product = $line['product'];

                // FIX: Check both vendor_id and user_id to ensure vendor_id is NEVER null!
                $vendorId = $product->vendor_id ?? $product->user_id;

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $product->id,
                    'vendor_id'    => $vendorId,
                    'product_name' => $product->title,
                    'quantity'     => $line['quantity'],
                    'price'        => $product->price,
                    'status'       => 'pending',
                ]);

                $product->decrementStock($line['quantity']);
            }

            return response()->json([
                'message'  => 'Order placed successfully.',
                'order_id' => $order->id,
                'id'       => $order->id,
                'data'     => $order->load('items'),
            ], 201);
        });
    }

    /**
     * GET /my-orders
     * Logged-in customer's own orders.
     */
    public function userOrders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * GET /orders
     * Admin gets all orders; customer gets their own orders.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin' || $user->is_super_admin === true;

        $query = Order::with(['items', 'user:id,name,email'])->orderByDesc('created_at');

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * GET /orders/{order}
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin' || $user->is_super_admin === true;

        if (!$isAdmin && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $order->load('items.product', 'user:id,name,email')]);
    }

    /**
     * GET /vendor/orders
     * Eager-loads order.user so customer details appear correctly on the vendor side.
     */
    public function vendorOrders(Request $request)
    {
        $items = OrderItem::where('vendor_id', $request->user()->id)
            ->with(['order:id,status,created_at,user_id', 'order.user:id,name,email', 'product:id,title,image'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $items]);
    }

    /**
     * PUT/PATCH /vendor/orders/{orderItem}/status
     */
    public function updateItemStatus(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'You do not own this order item.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        $orderItem->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Order item status updated.',
            'data'    => $orderItem->fresh(),
        ]);
    }

    /**
     * PUT/PATCH /admin/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        $order->update(['status' => $validated['status']]);
        
        // Also update items status in sync
        $order->items()->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Order status updated.',
            'data'    => $order->fresh('items'),
        ]);
    }

    /**
     * DELETE /admin/orders/{order}
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json(['message' => 'Order deleted.']);
    }

    /**
     * DELETE /admin/orders
     */
    public function destroyAll(Request $request)
    {
        $request->validate([
            'confirm' => 'required|in:DELETE_ALL_ORDERS',
        ]);

        Order::query()->delete();

        return response()->json(['message' => 'All orders deleted.']);
    }
}