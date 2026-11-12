<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use FelixMuhoro\Mpesa\Exceptions\AuthException;
use FelixMuhoro\Mpesa\Exceptions\MpesaException;
use FelixMuhoro\Mpesa\Mpesa;
use Illuminate\Support\Facades\Log;
use Throwable;

class MpesaService
{
    protected Mpesa $mpesa;

    protected PaymentLifecycleService $lifecycle;

    public function __construct(
        ?Mpesa $mpesa = null,
        ?PaymentLifecycleService $lifecycle = null
    ) {
        $this->mpesa = $mpesa ?? app(Mpesa::class);
        $this->lifecycle = $lifecycle ?? app(PaymentLifecycleService::class);
    }

    /**
     * Initiate STK Push for the complete order.
     */
    public function initiateStkPush(Payment $payment): array
    {
        $order = $payment->order()
            ->with(['user', 'items.product'])
            ->firstOrFail();

        $user = $order->user;
        $phone = $user?->phone;

        if (! is_string($phone) || trim($phone) === '') {
            return [
                'success' => false,
                'message' => 'Your account has no registered phone number. Please update your profile before making an M-Pesa payment.',
            ];
        }

        $phone = trim($phone);

        $amount = $payment->amount;
        $reference = "Order_{$order->id}";
        $description = $this->productDescription($order);

        try {
            $response = $this->mpesa->stkPush(
                phone: $phone,
                amount: $amount,
                reference: $reference,
                description: $description,
                callbackUrl: config('mpesa.stk.callback_url')
            );

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
                'message' => 'Failed to initiate M-Pesa payment. Please try again.',
            ];
        } catch (Throwable $e) {
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
     * Query the status of an STK Push.
     *
     * A failed query is not evidence of a failed payment.
     */
    public function queryStkPush(string $checkoutRequestId): array
    {
        try {
            $response = $this->mpesa->stkQuery($checkoutRequestId);

            $receiptNumber = null;
            $metadata = $response->raw['CallbackMetadata']['CallbackMetaData'] ?? null;

            if (is_array($metadata)) {
                foreach ($metadata as $entry) {
                    if (
                        isset($entry['Name'])
                        && $entry['Name'] === 'MpesaReceiptNumber'
                    ) {
                        $receiptNumber = $entry['Value'] ?? null;
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'result_code' => $response->resultCode,
                'result_desc' => $response->resultDesc,
                'checkout_request_id' => $response->checkoutRequestId,
                'merchant_request_id' => $response->merchantRequestId,
                'is_completed' => $response->isCompleted(),
                'is_failed' => $response->isFailed(),
                'is_pending' => $response->isPending(),
                'mpesa_receipt_number' => $receiptNumber,
            ];
        } catch (Throwable $e) {
            Log::error('M-Pesa STK Query failed', [
                'checkout_request_id' => $checkoutRequestId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'M-Pesa payment status is temporarily unavailable.',
            ];
        }
    }

    /**
     * Handle an STK callback from Safaricom.
     *
     * ResultCode 0 confirms success. A non-zero ResultCode in a valid
     * STK callback confirms failure. Missing or invalid result codes
     * must never trigger an inventory restoration.
     */
    public function handleCallback(array $data): array
    {
        $checkoutRequestId = $data['CheckoutRequestID'] ?? null;

        if (! is_string($checkoutRequestId) || $checkoutRequestId === '') {
            return [
                'success' => false,
                'message' => 'Missing CheckoutRequestID.',
            ];
        }

        $payment = Payment::where(
            'mpesa_checkout_request_id',
            $checkoutRequestId
        )->first();

        if (! $payment) {
            Log::warning('M-Pesa callback: Payment not found', [
                'checkout_request_id' => $checkoutRequestId,
            ]);

            return [
                'success' => false,
                'message' => 'Payment not found.',
            ];
        }

        if (! array_key_exists('ResultCode', $data)) {
            return [
                'success' => false,
                'message' => 'Missing M-Pesa result code.',
            ];
        }

        $resultCode = $data['ResultCode'];

        if (! is_numeric($resultCode)) {
            return [
                'success' => false,
                'message' => 'Invalid M-Pesa result code.',
            ];
        }

        $resultCode = (string) $resultCode;
        $resultDesc = $data['ResultDesc'] ?? null;
        $receiptNumber = $data['MpesaReceiptNumber'] ?? null;

        $attributes = [
            'mpesa_callback_data' => json_encode($data),
            'mpesa_result_code' => $resultCode,
            'mpesa_result_desc' => $resultDesc,
        ];

        if ($receiptNumber !== null) {
            $attributes['mpesa_receipt_number'] = $receiptNumber;
        }

        if ($resultCode === '0') {
            $this->lifecycle->markPaid($payment, $attributes);

            $payment = $payment->fresh([
                'order',
                'order.items.product',
                'order.user',
            ]);

            return [
                'success' => $payment->payment_status === 'paid',
                'payment' => $payment,
                'message' => $payment->payment_status === 'paid'
                    ? 'M-Pesa payment confirmed.'
                    : 'Payment was already processed with a different status.',
            ];
        }

        $this->lifecycle->markFailed($payment, $attributes);

        $payment = $payment->fresh([
            'order',
            'order.items.product',
            'order.user',
        ]);

        return [
            'success' => $payment->payment_status === 'failed',
            'message' => $resultDesc ?: 'M-Pesa payment failed.',
            'payment' => $payment,
        ];
    }

    /**
     * Handle a failed STK callback.
     *
     * Delegate to the same validation and lifecycle logic.
     */
    public function handleFailedCallback(array $data): array
    {
        if (! array_key_exists('ResultCode', $data)) {
            return [
                'success' => false,
                'message' => 'Missing M-Pesa result code.',
            ];
        }

        if (! is_numeric($data['ResultCode']) || (string) $data['ResultCode'] === '0') {
            return [
                'success' => false,
                'message' => 'This is not a confirmed failed M-Pesa callback.',
            ];
        }

        return $this->handleCallback($data);
    }

    /**
     * Get inline checkout information for the frontend.
     */
    public function getInlineCheckoutData(Payment $payment): array
    {
        $order = $payment->order()
            ->with(['user', 'items.product'])
            ->firstOrFail();

        $user = $order->user;

        return [
            'phone_number' => $user?->phone,
            'amount' => $payment->amount,
            'reference' => "Order_{$order->id}",
            'description' => $this->productDescription($order),
            'callback_url' => config('mpesa.stk.callback_url'),
        ];
    }

    /**
     * Build a description for new multi-item orders and legacy orders.
     */
    private function productDescription(Order $order): string
    {
        $items = $order->items ?? collect();

        if ($items->isNotEmpty()) {
            return $items->map(function ($item) {
                $name = $item->product_name
                    ?: $item->product?->name
                    ?: 'Product';

                return $name.' x '.$item->quantity;
            })->implode(', ');
        }

        return 'Payment for order #'.$order->id;
    }
}
