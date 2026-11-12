<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->unsignedInteger('subtotal')->nullable();
            $table->unsignedInteger('shipping')->nullable();
            $table->unsignedInteger('total')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('inventory_restored_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            // Preserve the name and price as they were when ordered.
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');

            $table->timestamps();

            $table->index(['order_id', 'product_id']);
        });

        // Backfill historical orders without changing their IDs or payments.
        DB::table('orders')
            ->orderBy('id')
            ->get()
            ->each(function ($order) {
                $product = DB::table('products')
                    ->where('id', $order->product_id)
                    ->first();

                if (! $product) {
                    throw new RuntimeException(
                        "Cannot backfill order {$order->id}: product not found."
                    );
                }

                $payment = DB::table('payments')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->first();

                $quantity = (int) $order->quantity;

                if ($quantity < 1) {
                    throw new RuntimeException(
                        "Cannot backfill order {$order->id}: invalid quantity."
                    );
                }

                $productPrice = is_numeric($product->price)
                    ? (int) round((float) $product->price)
                    : null;

                // Existing payment amount is the best available historical
                // price snapshot when it divides evenly by the quantity.
                if (
                    $payment &&
                    (int) $payment->amount >= 0 &&
                    (int) $payment->amount % $quantity === 0
                ) {
                    $unitPrice = intdiv((int) $payment->amount, $quantity);
                } else {
                    $unitPrice = $productPrice;
                }

                if ($unitPrice === null || $unitPrice < 0) {
                    throw new RuntimeException(
                        "Cannot backfill order {$order->id}: no valid historical price."
                    );
                }

                $subtotal = $payment
                    ? (int) $payment->amount
                    : $unitPrice * $quantity;

                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);

                // Historical orders had no separately recorded shipping.
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'subtotal' => $subtotal,
                        'shipping' => 0,
                        'total' => $subtotal,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('inventory_restored_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'delivery_address',
                'subtotal',
                'shipping',
                'total',
            ]);
        });
    }
};
