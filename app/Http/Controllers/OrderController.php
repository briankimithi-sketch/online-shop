<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     *
     * Admins see all orders.
     * Regular users see only their own orders.
     */
    public function index()
    {
        try {
            $query = Order::with([
                'product',
                'user',
                'payment',
            ])->latest();

            $user = request()->user();

            if ((int) $user->role_id !== 1) {
                $query->where('user_id', $user->id);
            }

            $orders = $query->get();

            return response()->json($orders);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new order.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
        ]);

        // Load the product to calculate the payment amount.
        $order->load('product');

        $amount = $order->product->price * $order->quantity;

        // Create a pending payment for the new order.
        Payment::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_status' => 'pending',
        ]);

        // Load the payment into the response.
        $order->load('payment');

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order,
        ], 201);
    }

    /**
     * Display the specified order.
     *
     * Admins can view any order.
     * Regular users can only view their own orders.
     */
    public function show(Order $order)
    {
        $user = request()->user();

        if (
            (int) $user->role_id !== 1 &&
            (int) $order->user_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You are not authorized to view this order.',
            ], 403);
        }

        return response()->json(
            $order->load([
                'product',
                'user',
                'payment',
            ])
        );
    }

    /**
     * Show the form for editing the specified order.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified order.
     *
     * Admins can update any order.
     * Regular users can only update their own orders.
     */
    public function update(Request $request, Order $order)
    {
        $user = $request->user();

        if (
            (int) $user->role_id !== 1 &&
            (int) $order->user_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You are not authorized to update this order.',
            ], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $order->update($validated);

        // Keep the payment amount synchronized with the order.
        $order->load('product');

        $payment = $order->payment;

        if ($payment) {
            $payment->update([
                'amount' => $order->product->price * $order->quantity,
            ]);
        }

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => $order->fresh([
                'product',
                'payment',
            ]),
        ], 200);
    }

    /**
     * Remove the specified order.
     *
     * Admins can delete any order.
     * Regular users can only delete their own orders.
     */
    public function destroy(Order $order)
    {
        $user = request()->user();

        if (
            (int) $user->role_id !== 1 &&
            (int) $order->user_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You are not authorized to delete this order.',
            ], 403);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully.',
        ], 200);
    }
}

