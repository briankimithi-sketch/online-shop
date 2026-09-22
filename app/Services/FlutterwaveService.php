<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Flutterwave\Payments\Flutterwave;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected Flutterwave $flutterwave;

    public function __construct()
    {
        $this->flutterwave = new Flutterwave;
    }

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Payment $payment): array
    {
        $order = $payment->order()->with('user', 'product')->firstOrFail();
        $user = $order->user;

        $txRef = $this->flutterwave->generateTransactionReference(
            config('flutterwave.transactionPrefix')
        );

        $payment->update([
            'flutterwave_tx_ref' => $txRef,
            'flutterwave_meta' => [
                'order_id' => $order->id,
                'product_name' => $order->product->name,
                'user_email' => $user->email,
            ],
        ]);

        // Amount is stored in base currency units (e.g., KES), Flutterwave expects amount in base units
        $amount = $payment->amount;

        $paymentData = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => config('flutterwave.currency'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'customer' => [
                'email' => $user->email,
                'name' => $user->name,
                'phone_number' => $user->phone,
            ],
            'customizations' => [
                'title' => config('flutterwave.title'),
                'description' => config('flutterwave.description'),
                'logo' => config('flutterwave.logo'),
            ],
            'meta' => $payment->flutterwave_meta,
        ];

        try {
            // Use standard redirect (hosted checkout)
            $paymentLink = $this->flutterwave->use('modals')->render($paymentData, 'standard');

            $payment->update([
                'flutterwave_payment_link' => $paymentLink,
            ]);

            return [
                'success' => true,
                'payment_link' => $paymentLink,
                'tx_ref' => $txRef,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave payment initiation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate payment: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment via Flutterwave
     */
    public function verifyPayment(string $txRef): array
    {
        try {
            $response = $this->flutterwave->verifyTransactionReference($txRef);

            if (isset($response['status']) && $response['status'] === 'success') {
                $data = $response['data'];

                return [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $data['id'] ?? null,
                        'tx_ref' => $data['tx_ref'] ?? $txRef,
                        'amount' => $data['amount'] ?? 0,
                        'currency' => $data['currency'] ?? '',
                        'status' => $data['status'] ?? '',
                        'payment_type' => $data['payment_type'] ?? '',
                        'customer' => $data['customer'] ?? [],
                        'meta' => $data['meta'] ?? [],
                        'created_at' => $data['created_at'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? 'Payment verification failed',
                'data' => $response['data'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave verification failed', [
                'tx_ref' => $txRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Verification error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful payment callback
     */
    public function handleCallback(array $data): array
    {
        $txRef = $data['tx_ref'] ?? null;

        if (! $txRef) {
            return ['success' => false, 'message' => 'Missing transaction reference'];
        }

        $payment = Payment::where('flutterwave_tx_ref', $txRef)->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        // If already paid, return success
        if ($payment->payment_status === 'paid') {
            return [
                'success' => true,
                'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
                'message' => 'Payment already processed',
            ];
        }

        // Verify with Flutterwave to prevent spoofing
        $verification = $this->verifyPayment($txRef);

        if (! $verification['success']) {
            return ['success' => false, 'message' => 'Verification failed: '.$verification['message']];
        }

        $verifiedData = $verification['data'];

        // Verify amount matches
        if ((int) $verifiedData['amount'] !== (int) $payment->amount) {
            Log::warning('Flutterwave amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount,
                'received' => $verifiedData['amount'],
            ]);
            // Still process but log warning
        }

        // Update payment status
        $payment->update([
            'flutterwave_transaction_id' => $verifiedData['transaction_id'],
            'payment_status' => 'paid',
            'paid_at' => now(),
            'flutterwave_meta' => array_merge($payment->flutterwave_meta ?? [], $verifiedData),
        ]);

        // Update order status if needed
        $order = $payment->order;
        if ($order && $order->status !== 'paid') {
            $order->update(['status' => 'paid']);
        }

        return [
            'success' => true,
            'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
        ];
    }

    /**
     * Handle failed payment callback
     */
    public function handleFailedCallback(array $data): array
    {
        $txRef = $data['tx_ref'] ?? null;

        if (! $txRef) {
            return ['success' => false, 'message' => 'Missing transaction reference'];
        }

        $payment = Payment::where('flutterwave_tx_ref', $txRef)->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update([
            'payment_status' => 'failed',
            'flutterwave_meta' => array_merge($payment->flutterwave_meta ?? [], $data),
        ]);

        return [
            'success' => true,
            'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
        ];
    }

    /**
     * Get inline checkout data for frontend (Vue/React)
     */
    public function getInlineCheckoutData(Payment $payment): array
    {
        $order = $payment->order()->with('user', 'product')->firstOrFail();
        $user = $order->user;

        $txRef = $payment->flutterwave_tx_ref ?? $this->flutterwave->generateTransactionReference(
            config('flutterwave.transactionPrefix')
        );

        if (! $payment->flutterwave_tx_ref) {
            $payment->update(['flutterwave_tx_ref' => $txRef]);
        }

        // Amount is stored in base currency units
        $amount = $payment->amount;

        return [
            'public_key' => config('flutterwave.publicKey'),
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => config('flutterwave.currency'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'customer' => [
                'email' => $user->email,
                'name' => $user->name,
                'phone_number' => $user->phone,
            ],
            'customizations' => [
                'title' => config('flutterwave.title'),
                'description' => config('flutterwave.description'),
                'logo' => config('flutterwave.logo'),
            ],
            'meta' => $payment->flutterwave_meta ?? [],
            'payment_options' => config('flutterwave.paymentType'),
        ];
    }
}
