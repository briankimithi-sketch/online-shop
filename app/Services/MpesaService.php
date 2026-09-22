<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use FelixMuhoro\Mpesa\Exceptions\AuthException;
use FelixMuhoro\Mpesa\Exceptions\MpesaException;
use FelixMuhoro\Mpesa\Mpesa;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected Mpesa $mpesa;

    public function __construct()
    {
        $this->mpesa = app(Mpesa::class);
    }

    /**
     * Initiate STK Push payment for an order
     */
    public function initiateStkPush(Payment $payment): array
    {
        $order = $payment->order()->with('user', 'product')->firstOrFail();
        $user = $order->user;

        $phone = $user->phone;
        $amount = $payment->amount;
        $reference = "Order_{$order->id}";
        $description = "Payment for {$order->product->name}";

        try {
            $response = $this->mpesa->stkPush(
                phone: $phone,
                amount: $amount,
                reference: $reference,
                description: $description,
                callbackUrl: config('mpesa.stk.callback_url')
            );

            // Store checkout request ID for querying
            $payment->update([
                'mpesa_checkout_request_id' => $response->checkoutRequestId,
                'mpesa_merchant_request_id' => $response->merchantRequestId,
                'mpesa_phone_number' => $phone,
            ]);

            return [
                'success' => true,
                'message' => 'STK Push sent. Please enter your M-Pesa PIN.',
                'checkout_request_id' => $response->checkoutRequestId,
                'merchant_request_id' => $response->merchantRequestId,
                'response_code' => $response->responseCode,
                'response_description' => $response->responseDescription,
                'customer_message' => $response->customerMessage,
            ];
        } catch (AuthException $e) {
            Log::error('M-Pesa authentication failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'M-Pesa configuration error. Please contact support.',
            ];
        } catch (MpesaException $e) {
            Log::error('M-Pesa STK Push failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate payment: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa unexpected error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ];
        }
    }

    /**
     * Query STK Push status
     */
    public function queryStkPush(string $checkoutRequestId): array
    {
        try {
            $response = $this->mpesa->stkQuery($checkoutRequestId);

            return [
                'success' => true,
                'result_code' => $response->resultCode,
                'result_desc' => $response->resultDesc,
                'checkout_request_id' => $response->checkoutRequestId,
                'merchant_request_id' => $response->merchantRequestId,
                'is_completed' => $response->isCompleted(),
                'is_failed' => $response->isFailed(),
                'is_pending' => $response->isPending(),
                'mpesa_receipt_number' => $response->mpesaReceiptNumber,
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa STK Query failed', [
                'checkout_request_id' => $checkoutRequestId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Query failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful payment callback
     */
    public function handleCallback(array $data): array
    {
        $checkoutRequestId = $data['CheckoutRequestID'] ?? null;

        if (! $checkoutRequestId) {
            return ['success' => false, 'message' => 'Missing CheckoutRequestID'];
        }

        $payment = Payment::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if (! $payment) {
            Log::warning('M-Pesa callback: Payment not found', ['checkout_request_id' => $checkoutRequestId]);

            return ['success' => false, 'message' => 'Payment not found'];
        }

        // If already processed, return success
        if ($payment->payment_status === 'paid') {
            return [
                'success' => true,
                'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
                'message' => 'Payment already processed',
            ];
        }

        $resultCode = $data['ResultCode'] ?? null;
        $resultDesc = $data['ResultDesc'] ?? null;
        $mpesaReceiptNumber = $data['MpesaReceiptNumber'] ?? null;

        // Store callback data
        $payment->update([
            'mpesa_receipt_number' => $mpesaReceiptNumber,
            'mpesa_callback_data' => json_encode($data),
            'mpesa_result_code' => $resultCode,
            'mpesa_result_desc' => $resultDesc,
        ]);

        if ($resultCode === '0' || $resultCode === 0) {
            // Payment successful
            $payment->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            // Update order status
            $order = $payment->order;
            if ($order && $order->status !== 'paid') {
                $order->update(['status' => 'paid']);
            }

            return [
                'success' => true,
                'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
            ];
        } else {
            // Payment failed
            $payment->update([
                'payment_status' => 'failed',
            ]);

            return [
                'success' => false,
                'message' => $resultDesc ?? 'Payment failed',
                'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
            ];
        }
    }

    /**
     * Handle failed payment callback
     */
    public function handleFailedCallback(array $data): array
    {
        $checkoutRequestId = $data['CheckoutRequestID'] ?? null;

        if (! $checkoutRequestId) {
            return ['success' => false, 'message' => 'Missing CheckoutRequestID'];
        }

        $payment = Payment::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->update([
            'payment_status' => 'failed',
            'mpesa_callback_data' => json_encode($data),
            'mpesa_result_code' => $data['ResultCode'] ?? null,
            'mpesa_result_desc' => $data['ResultDesc'] ?? null,
        ]);

        return [
            'success' => true,
            'payment' => $payment->fresh(['order', 'order.product', 'order.user']),
        ];
    }

    /**
     * Get inline checkout data for frontend (shows phone number for user to enter PIN)
     */
    public function getInlineCheckoutData(Payment $payment): array
    {
        $order = $payment->order()->with('user', 'product')->firstOrFail();
        $user = $order->user;

        return [
            'phone_number' => $user->phone,
            'amount' => $payment->amount,
            'reference' => "Order_{$order->id}",
            'description' => "Payment for {$order->product->name}",
            'callback_url' => config('mpesa.stk.callback_url'),
        ];
    }
}
