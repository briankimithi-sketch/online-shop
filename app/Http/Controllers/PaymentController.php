<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Payment Listing
    |--------------------------------------------------------------------------
    */

    /**
     * Display payments.
     *
     * Admins see all payments.
     * Regular users see only their own payments.
     */
    public function index()
    {
        $user = request()->user();

        $query = Payment::with([
            'order',
            'order.product',
            'order.user',
        ])->latest();

        if (!$this->isAdmin($user)) {
            $query->whereHas('order', function ($orderQuery) use ($user) {
                $orderQuery->where('user_id', $user->id);
            });
        }

        return response()->json($query->get());
    }

    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Create a payment.
     *
     * Only admins can create payments manually.
     */
    public function store(Request $request)
    {
        if (!$this->isAdmin($request->user())) {
            return $this->adminOnlyResponse();
        }

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_status' => 'required|string|in:pending,paid,failed',
        ]);

        $payment = Payment::create($validated);

        return response()->json([
            'message' => 'Payment created successfully.',
            'payment' => $payment->load([
                'order',
                'order.product',
                'order.user',
            ]),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | View Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Display a specific payment.
     *
     * Admins can view any payment.
     * Regular users can view only their own payments.
     */
    public function show(Payment $payment)
    {
        $user = request()->user();

        $payment->load([
            'order',
            'order.product',
            'order.user',
        ]);

        if (!$this->canAccessPayment($user, $payment)) {
            return response()->json([
                'message' => 'You are not authorized to view this payment.',
            ], 403);
        }

        return response()->json($payment);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Update a payment.
     *
     * Only admins can update payments.
     */
    public function update(Request $request, Payment $payment)
    {
        if (!$this->isAdmin($request->user())) {
            return $this->adminOnlyResponse();
        }

        $validated = $request->validate([
            'order_id' => 'sometimes|integer|exists:orders,id',
            'amount' => 'sometimes|numeric|min:0',
            'payment_status' => 'sometimes|string|in:pending,paid,failed',
        ]);

        $payment->update($validated);

        return response()->json([
            'message' => 'Payment updated successfully.',
            'payment' => $payment->fresh([
                'order',
                'order.product',
                'order.user',
            ]),
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Demo Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Process a demo payment.
     *
     * Admins can pay any payment.
     * Regular users can pay only their own payments.
     */
    public function demoPay(Payment $payment)
    {
        $user = request()->user();

        $payment->load('order');

        if (!$this->canAccessPayment($user, $payment)) {
            return response()->json([
                'message' => 'You are not authorized to pay this order.',
            ], 403);
        }

        if ($payment->payment_status !== 'pending') {
            return response()->json([
                'message' => 'This payment has already been processed.',
                'payment' => $payment,
            ], 400);
        }

        $payment->update([
            'payment_status' => 'paid',
        ]);

        return response()->json([
            'message' => 'Demo payment successful.',
            'payment' => $payment->fresh([
                'order',
            ]),
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Delete a payment.
     *
     * Only admins can delete payments.
     */
    public function destroy(Payment $payment)
    {
        if (!$this->isAdmin(request()->user())) {
            return $this->adminOnlyResponse();
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully.',
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the user is an administrator.
     */
    private function isAdmin($user): bool
    {
        return $user && (int) $user->role_id === 1;
    }

    /**
     * Determine whether the user can access a payment.
     */
    private function canAccessPayment($user, Payment $payment): bool
    {
        // Admins can access every payment.
        if ($this->isAdmin($user)) {
            return true;
        }

        // Payment must have an order.
        if (!$payment->order) {
            return false;
        }

        // Regular users can access only their own orders.
        return (int) $payment->order->user_id === (int) $user->id;
    }

    /**
     * Standard response for admin-only actions.
     */
    private function adminOnlyResponse()
    {
        return response()->json([
            'message' => 'Only administrators can perform this action.',
        ], 403);
    }
}
