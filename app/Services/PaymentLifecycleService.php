<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use LogicException;

class PaymentLifecycleService
{
    /**
     * Provider fields that may be saved atomically with a payment transition.
     */
    private const PROVIDER_ATTRIBUTES = [
        'flutterwave_transaction_id',
        'flutterwave_meta',
        'mpesa_receipt_number',
        'mpesa_callback_data',
        'mpesa_result_code',
        'mpesa_result_desc',
    ];

    /**
     * Mark a pending payment as paid.
     *
     * A final payment state is never reversed by a later callback.
     */
    public function markPaid(
        Payment $payment,
        array $providerAttributes = []
    ): array {
        return DB::transaction(function () use ($payment, $providerAttributes) {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->payment_status !== 'pending') {
                return [
                    'transitioned' => false,
                    'status' => $lockedPayment->payment_status,
                ];
            }

            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyProviderAttributes(
                $lockedPayment,
                $providerAttributes
            );

            $lockedPayment->payment_status = 'paid';
            $lockedPayment->paid_at = $lockedPayment->paid_at ?? now();
            $lockedPayment->save();

            $order->status = 'paid';
            $order->save();

            return [
                'transitioned' => true,
                'status' => 'paid',
            ];
        }, 3);
    }

    /**
     * Mark a confirmed failed payment and restore its reserved stock once.
     *
     * Do not call this for timeouts, network errors, or pending provider
     * responses. Only confirmed provider failures belong here.
     */
    public function markFailed(
        Payment $payment,
        array $providerAttributes = []
    ): array {
        return DB::transaction(function () use ($payment, $providerAttributes) {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->payment_status !== 'pending') {
                return [
                    'transitioned' => false,
                    'status' => $lockedPayment->payment_status,
                ];
            }

            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Restore inventory only if this payment has not already
            // recorded a restoration.
            if ($lockedPayment->inventory_restored_at === null) {
                $items = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->orderBy('product_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    // Compatibility fallback for orders created before
                    // order_items existed.
                    $this->restoreLegacyOrderStock($order);
                } else {
                    foreach ($items as $item) {
                        $this->restoreProductStock(
                            (int) $item->product_id,
                            (int) $item->quantity
                        );
                    }
                }

                $lockedPayment->inventory_restored_at = now();
            }

            $this->applyProviderAttributes(
                $lockedPayment,
                $providerAttributes
            );

            $lockedPayment->payment_status = 'failed';
            $lockedPayment->save();

            $order->status = 'failed';
            $order->save();

            return [
                'transitioned' => true,
                'status' => 'failed',
            ];
        }, 3);
    }

    /**
     * Restore stock for a legacy single-product order.
     */
    private function restoreLegacyOrderStock(Order $order): void
    {
        if (! $order->product_id || (int) $order->quantity < 1) {
            throw new LogicException(
                "Order {$order->id} has no valid inventory details."
            );
        }

        $this->restoreProductStock(
            (int) $order->product_id,
            (int) $order->quantity
        );
    }

    /**
     * Restore stock, including products that were soft-deleted.
     */
    private function restoreProductStock(
        int $productId,
        int $quantity
    ): void {
        if ($quantity < 1) {
            throw new LogicException(
                "Cannot restore an invalid quantity for product {$productId}."
            );
        }

        $product = Product::withTrashed()
            ->whereKey($productId)
            ->lockForUpdate()
            ->first();

        if (! $product) {
            throw new LogicException(
                "Cannot restore stock: product {$productId} no longer exists."
            );
        }

        $maximumStock = 4294967295;
        $currentStock = (int) $product->stock;

        if ($currentStock > $maximumStock - $quantity) {
            throw new LogicException(
                "Restoring stock would exceed the database limit for product {$productId}."
            );
        }

        $product->stock = $currentStock + $quantity;
        $product->save();
    }

    /**
     * Apply only provider-specific fields, never lifecycle fields.
     */
    private function applyProviderAttributes(
        Payment $payment,
        array $attributes
    ): void {
        $allowed = array_intersect_key(
            $attributes,
            array_flip(self::PROVIDER_ATTRIBUTES)
        );

        if ($allowed !== []) {
            $payment->fill($allowed);
        }
    }
}
