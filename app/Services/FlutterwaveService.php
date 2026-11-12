<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Flutterwave\Payments\Flutterwave;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlutterwaveService
{
    protected Flutterwave $flutterwave;

    protected PaymentLifecycleService $lifecycle;

    public function __construct(
        ?Flutterwave $flutterwave = null,
        ?PaymentLifecycleService $lifecycle = null
    ) {
        $this->flutterwave = $flutterwave ?? new Flutterwave;
        $this->lifecycle = $lifecycle ?? app(PaymentLifecycleService::class);
    }

    /**
     * Initiate Flutterwave checkout for the complete order.
     */
    public function initiatePayment(Payment $payment): array
    {
        $order = $payment->order()
            ->with(['user', 'items.product'])
            ->firstOrFail();

        $user = $order->user;

        try {
            $txRef = $payment->flutterwave_tx_ref
                ?: $this->flutterwave->generateTransactionReference(
                    config('flutterwave.transactionPrefix')
                );

            $meta = array_merge($payment->flutterwave_meta ?? [], [
                'order_id' => $order->id,
                'user_email' => $user?->email,
            ]);

            $payment->update([
                'flutterwave_tx_ref' => $txRef,
                'flutterwave_meta' => $meta,
            ]);

            $paymentData = [
                'tx_ref' => $txRef,
                'amount' => $payment->amount,
                'currency' => config('flutterwave.currency'),
                'redirect_url' => config('flutterwave.redirectUrl'),
                'customer' => [
                    'email' => $user?->email,
                    'name' => $user?->name,
                    'phone_number' => $order->phone ?: $user?->phone,
                ],
                'customizations' => [
                    'title' => config('flutterwave.title'),
                    'description' => $this->productDescription($order),
                    'logo' => config('flutterwave.logo'),
                ],
                'meta' => $meta,
            ];

            $paymentLink = $this->flutterwave
                ->use('modals')
                ->render($paymentData, 'standard');

            $payment->update([
                'flutterwave_payment_link' => $paymentLink,
            ]);

            return [
                'success' => true,
                'payment_link' => $paymentLink,
                'tx_ref' => $txRef,
            ];
        } catch (Throwable $e) {
            Log::error('Flutterwave payment initiation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Flutterwave could not be started. Please try again or contact support.',
            ];
        }
    }

    /**
     * Verify a transaction directly with Flutterwave.
     */
    public function verifyPayment(string $txRef): array
    {
        try {
            $response = $this->flutterwave->verifyTransactionReference($txRef);

            if (($response['status'] ?? null) !== 'success') {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Payment verification failed.',
                    'data' => $response['data'] ?? null,
                ];
            }

            $data = $response['data'] ?? [];

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $data['id'] ?? null,
                    'tx_ref' => $data['tx_ref'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'status' => $data['status'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'customer' => $data['customer'] ?? [],
                    'meta' => $data['meta'] ?? [],
                    'created_at' => $data['created_at'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Flutterwave verification failed', [
                'tx_ref' => $txRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Flutterwave verification is temporarily unavailable.',
            ];
        }
    }

    /**
     * Handle a successful redirect or webhook after server-side verification.
     */
    public function handleCallback(array $data): array
    {
        $txRef = $data['tx_ref'] ?? null;

        if (! is_string($txRef) || $txRef === '') {
            return [
                'success' => false,
                'message' => 'Missing transaction reference.',
            ];
        }

        $payment = Payment::where('flutterwave_tx_ref', $txRef)->first();

        if (! $payment) {
            return [
                'success' => false,
                'message' => 'Payment not found.',
            ];
        }

        if ($payment->payment_status === 'paid') {
            return [
                'success' => true,
                'payment' => $payment->fresh(['order', 'order.items.product', 'order.user']),
                'message' => 'Payment already processed.',
            ];
        }

        if ($payment->payment_status !== 'pending') {
            return [
                'success' => false,
                'message' => 'This payment is no longer pending.',
                'payment' => $payment->fresh(['order']),
            ];
        }

        $verification = $this->verifyPayment($txRef);

        if (! $verification['success']) {
            return [
                'success' => false,
                'message' => $verification['message'],
            ];
        }

        $verified = $verification['data'];

        $amountMatches = is_numeric($verified['amount'])
            && number_format((float) $verified['amount'], 2, '.', '')
                === number_format((float) $payment->amount, 2, '.', '');

        $currencyMatches = strtoupper((string) $verified['currency'])
            === strtoupper((string) config('flutterwave.currency'));

        $referenceMatches = hash_equals(
            (string) $payment->flutterwave_tx_ref,
            (string) $verified['tx_ref']
        );

        $transactionId = $verified['transaction_id'];

        if (
            $verified['status'] !== 'successful'
            || ! $amountMatches
            || ! $currencyMatches
            || ! $referenceMatches
            || ! is_scalar($transactionId)
            || (string) $transactionId === ''
        ) {
            Log::warning('Flutterwave verification did not satisfy payment requirements', [
                'payment_id' => $payment->id,
                'status' => $verified['status'],
                'amount_matches' => $amountMatches,
                'currency_matches' => $currencyMatches,
                'reference_matches' => $referenceMatches,
                'has_transaction_id' => is_scalar($transactionId)
                    && (string) $transactionId !== '',
            ]);

            return [
                'success' => false,
                'message' => 'Flutterwave could not confirm this payment.',
            ];
        }

        $this->lifecycle->markPaid($payment, [
            'flutterwave_transaction_id' => (string) $transactionId,
            'flutterwave_meta' => array_merge(
                $payment->flutterwave_meta ?? [],
                $verified
            ),
        ]);

        $payment = $payment->fresh(['order', 'order.items.product', 'order.user']);

        return [
            'success' => $payment->payment_status === 'paid',
            'payment' => $payment,
            'message' => $payment->payment_status === 'paid'
                ? 'Payment successfully verified.'
                : 'Payment was already processed with a different status.',
        ];
    }

    /**
     * Handle a failed callback only after Flutterwave confirms failure.
     */
    public function handleFailedCallback(array $data): array
    {
        $txRef = $data['tx_ref'] ?? null;

        if (! is_string($txRef) || $txRef === '') {
            return [
                'success' => false,
                'message' => 'Missing transaction reference.',
            ];
        }

        $payment = Payment::where('flutterwave_tx_ref', $txRef)->first();

        if (! $payment) {
            return [
                'success' => false,
                'message' => 'Payment not found.',
            ];
        }

        if ($payment->payment_status !== 'pending') {
            return [
                'success' => $payment->payment_status === 'failed',
                'payment' => $payment->fresh(['order']),
                'message' => 'Payment has already been processed.',
            ];
        }

        $verification = $this->verifyPayment($txRef);

        if (! $verification['success']) {
            return [
                'success' => false,
                'message' => 'The payment status could not be verified.',
            ];
        }

        $verified = $verification['data'];

        if (
            ($verified['tx_ref'] ?? null) !== $payment->flutterwave_tx_ref
            || ($verified['status'] ?? null) !== 'failed'
        ) {
            return [
                'success' => false,
                'message' => 'Flutterwave has not confirmed a failed payment.',
            ];
        }

        $this->lifecycle->markFailed($payment, [
            'flutterwave_meta' => array_merge(
                $payment->flutterwave_meta ?? [],
                $verified,
                ['callback_data' => $data]
            ),
        ]);

        $payment = $payment->fresh(['order', 'order.items.product', 'order.user']);

        return [
            'success' => $payment->payment_status === 'failed',
            'payment' => $payment,
            'message' => 'Failed payment processed.',
        ];
    }

    /**
     * Build inline checkout data for the frontend.
     */
    public function getInlineCheckoutData(Payment $payment): array
    {
        $order = $payment->order()
            ->with(['user', 'items.product'])
            ->firstOrFail();

        $user = $order->user;

        $txRef = $payment->flutterwave_tx_ref
            ?: $this->flutterwave->generateTransactionReference(
                config('flutterwave.transactionPrefix')
            );

        if (! $payment->flutterwave_tx_ref) {
            $payment->update(['flutterwave_tx_ref' => $txRef]);
        }

        return [
            'public_key' => config('flutterwave.publicKey'),
            'tx_ref' => $txRef,
            'amount' => $payment->amount,
            'currency' => config('flutterwave.currency'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'customer' => [
                'email' => $user?->email,
                'name' => $user?->name,
                'phone_number' => $order->phone ?: $user?->phone,
            ],
            'customizations' => [
                'title' => config('flutterwave.title'),
                'description' => $this->productDescription($order),
                'logo' => config('flutterwave.logo'),
            ],
            'meta' => $payment->flutterwave_meta ?? [],
            'payment_options' => config('flutterwave.paymentType'),
        ];
    }

    /**
     * Create a readable description for both new and legacy orders.
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
