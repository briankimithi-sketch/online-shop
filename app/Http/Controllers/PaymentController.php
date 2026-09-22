<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\FlutterwaveService;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::with([
            'order',
            'order.product',
            'order.user',
        ])->latest();

        if (! $this->isAdmin($user)) {
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
        if (! $this->isAdmin($request->user())) {
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
    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();

        $payment->load([
            'order',
            'order.product',
            'order.user',
        ]);

        if (! $this->canAccessPayment($user, $payment)) {
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
        if (! $this->isAdmin($request->user())) {
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
    | Create Payment (Empty - Part of Resource)
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Not applicable for API'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Payment (Empty - Part of Resource)
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        return response()->json(['message' => 'Not applicable for API'], 404);
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

        if (! $this->canAccessPayment($user, $payment)) {
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
    | Flutterwave Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate Flutterwave payment (standard redirect).
     */
    public function flutterwaveInitiate(Request $request, Payment $payment, FlutterwaveService $flutterwave)
    {
        $user = $request->user();

        $payment->load('order');

        if (! $this->canAccessPayment($user, $payment)) {
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

        $result = $flutterwave->initiatePayment($payment);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'message' => 'Payment initiated. Redirect to payment link.',
            'payment_link' => $result['payment_link'],
            'tx_ref' => $result['tx_ref'],
            'payment' => $payment->fresh(['order']),
        ], 200);
    }

    /**
     * Get Flutterwave inline checkout data for frontend.
     */
    public function flutterwaveInlineData(Request $request, Payment $payment, FlutterwaveService $flutterwave)
    {
        $user = $request->user();

        $payment->load('order');

        if (! $this->canAccessPayment($user, $payment)) {
            return response()->json([
                'message' => 'You are not authorized to view this payment.',
            ], 403);
        }

        if ($payment->payment_status !== 'pending') {
            return response()->json([
                'message' => 'This payment has already been processed.',
                'payment' => $payment,
            ], 400);
        }

        $data = $flutterwave->getInlineCheckoutData($payment);

        return response()->json([
            'message' => 'Flutterwave inline checkout data.',
            'flutterwave_data' => $data,
        ], 200);
    }

    /**
     * Flutterwave callback handler (called by Flutterwave after payment).
     */
    public function flutterwaveCallback(Request $request, FlutterwaveService $flutterwave)
    {
        // Flutterwave redirects here with transaction data in query params
        $data = $request->query();

        $txRef = $data['tx_ref'] ?? null;

        if (! $txRef) {
            Log::warning('Flutterwave callback: Missing tx_ref', ['query' => $data]);

            return redirect(config('flutterwave.cancelUrl').'?error=missing_tx_ref');
        }

        $result = $flutterwave->handleCallback($data);

        if ($result['success']) {
            // Redirect to frontend success page
            return redirect(config('flutterwave.successUrl').'?tx_ref='.$txRef.'&status=success');
        }

        // Redirect to frontend cancel/failed page
        return redirect(config('flutterwave.cancelUrl').'?tx_ref='.$txRef.'&status=failed&message='.urlencode($result['message'] ?? 'Payment verification failed'));
    }

    /**
     * Flutterwave success page (user lands here after successful payment).
     */
    public function flutterwaveSuccess(Request $request)
    {
        return response()->json([
            'message' => 'Payment successful!',
            'tx_ref' => $request->query('tx_ref'),
            'status' => 'success',
        ]);
    }

    /**
     * Flutterwave cancel page (user lands here after cancelled payment).
     */
    public function flutterwaveCancel(Request $request)
    {
        return response()->json([
            'message' => 'Payment cancelled or failed.',
            'tx_ref' => $request->query('tx_ref'),
            'status' => 'cancelled',
        ]);
    }

    /**
     * Flutterwave webhook handler (server-to-server notifications).
     */
    public function flutterwaveWebhook(Request $request, FlutterwaveService $flutterwave)
    {
        // Verify webhook signature
        $signature = $request->header('verif-hash');
        $secretHash = config('flutterwave.secretHash');

        if ($signature !== $secretHash) {
            Log::warning('Flutterwave webhook: Invalid signature', [
                'received' => $signature,
                'expected' => $secretHash,
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        // Process based on event type
        $event = $payload['event'] ?? null;
        $txRef = $payload['data']['tx_ref'] ?? null;

        if (! $txRef) {
            Log::warning('Flutterwave webhook: Missing tx_ref in payload', ['event' => $event]);

            return response()->json(['message' => 'Missing tx_ref'], 400);
        }

        // Handle different event types
        switch ($event) {
            case 'charge.completed':
            case 'payment.successful':
                $result = $flutterwave->handleCallback(['tx_ref' => $txRef]);

                Log::info('Flutterwave webhook: Payment successful', [
                    'event' => $event,
                    'tx_ref' => $txRef,
                    'success' => $result['success'],
                ]);
                break;

            case 'payment.failed':
            case 'charge.failed':
                $payment = Payment::where('flutterwave_tx_ref', $txRef)->first();
                if ($payment) {
                    $payment->update([
                        'payment_status' => 'failed',
                        'flutterwave_meta' => array_merge($payment->flutterwave_meta ?? [], $payload['data'] ?? []),
                    ]);
                }

                Log::info('Flutterwave webhook: Payment failed', [
                    'event' => $event,
                    'tx_ref' => $txRef,
                ]);
                break;

            case 'transfer.completed':
            case 'transfer.failed':
                // Handle transfer events if needed
                Log::info('Flutterwave webhook: Transfer event', [
                    'event' => $event,
                    'tx_ref' => $txRef,
                ]);
                break;

            default:
                Log::info('Flutterwave webhook: Unhandled event', [
                    'event' => $event,
                    'tx_ref' => $txRef,
                ]);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Payment
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate M-Pesa STK Push payment.
     */
    public function mpesaStkPush(Request $request, Payment $payment, MpesaService $mpesa)
    {
        $user = $request->user();

        $payment->load('order');

        if (! $this->canAccessPayment($user, $payment)) {
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

        $result = $mpesa->initiateStkPush($payment);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'message' => $result['message'],
            'checkout_request_id' => $result['checkout_request_id'],
            'merchant_request_id' => $result['merchant_request_id'],
            'response_code' => $result['response_code'],
            'response_description' => $result['response_description'],
            'customer_message' => $result['customer_message'],
            'payment' => $payment->fresh(['order']),
        ], 200);
    }

    /**
     * Query M-Pesa STK Push status.
     */
    public function mpesaQuery(Request $request, Payment $payment, MpesaService $mpesa)
    {
        $user = $request->user();

        $payment->load('order');

        if (! $this->canAccessPayment($user, $payment)) {
            return response()->json([
                'message' => 'You are not authorized to view this payment.',
            ], 403);
        }

        $checkoutRequestId = $payment->mpesa_checkout_request_id;

        if (! $checkoutRequestId) {
            return response()->json([
                'message' => 'No STK Push initiated for this payment.',
            ], 400);
        }

        $result = $mpesa->queryStkPush($checkoutRequestId);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 500);
        }

        // If completed, update payment
        if ($result['is_completed']) {
            $payment->update([
                'payment_status' => 'paid',
                'mpesa_receipt_number' => $result['mpesa_receipt_number'],
                'paid_at' => now(),
            ]);

            $order = $payment->order;
            if ($order && $order->status !== 'paid') {
                $order->update(['status' => 'paid']);
            }
        }

        return response()->json([
            'message' => 'STK Push status retrieved.',
            'result_code' => $result['result_code'],
            'result_desc' => $result['result_desc'],
            'is_completed' => $result['is_completed'],
            'is_failed' => $result['is_failed'],
            'is_pending' => $result['is_pending'],
            'mpesa_receipt_number' => $result['mpesa_receipt_number'],
            'payment' => $payment->fresh(['order']),
        ], 200);
    }

    /**
     * Get M-Pesa inline checkout data for frontend.
     */
    public function mpesaInlineData(Request $request, Payment $payment, MpesaService $mpesa)
    {
        $user = $request->user();

        $payment->load('order');

        if (! $this->canAccessPayment($user, $payment)) {
            return response()->json([
                'message' => 'You are not authorized to view this payment.',
            ], 403);
        }

        if ($payment->payment_status !== 'pending') {
            return response()->json([
                'message' => 'This payment has already been processed.',
                'payment' => $payment,
            ], 400);
        }

        $data = $mpesa->getInlineCheckoutData($payment);

        return response()->json([
            'message' => 'M-Pesa STK Push data.',
            'mpesa_data' => $data,
        ], 200);
    }

    /**
     * M-Pesa callback handler (called by Safaricom after payment).
     */
    public function mpesaCallback(Request $request, MpesaService $mpesa)
    {
        $data = $request->json()->all();

        Log::info('M-Pesa callback received', ['data' => $data]);

        $result = $mpesa->handleCallback($data);

        if ($result['success']) {
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Callback processed successfully',
            ], 200);
        }

        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => $result['message'] ?? 'Callback processing failed',
        ], 200);
    }

    /**
     * M-Pesa validation handler (C2B).
     */
    public function mpesaValidation(Request $request)
    {
        $data = $request->json()->all();

        Log::info('M-Pesa validation received', ['data' => $data]);

        // For C2B, we can validate here (e.g., check if account exists)
        // For STK Push, this isn't typically used

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Validation successful',
        ], 200);
    }

    /**
     * M-Pesa confirmation handler (C2B).
     */
    public function mpesaConfirmation(Request $request, MpesaService $mpesa)
    {
        $data = $request->json()->all();

        Log::info('M-Pesa confirmation received', ['data' => $data]);

        // For C2B confirmation
        $result = $mpesa->handleCallback($data);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmation successful',
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
    public function destroy(Request $request, Payment $payment)
    {
        if (! $this->isAdmin($request->user())) {
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
        if (! $payment->order) {
            return false;
        }

        // Regular users can access only their own orders.
        return (int) ($payment->order->user_id ?? 0) === (int) ($user->id ?? 0);
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
