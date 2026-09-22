<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('flutterwave_tx_ref')->nullable()->unique()->after('payment_status');
            $table->string('flutterwave_transaction_id')->nullable()->after('flutterwave_tx_ref');
            $table->string('flutterwave_payment_link')->nullable()->after('flutterwave_transaction_id');
            $table->json('flutterwave_meta')->nullable()->after('flutterwave_payment_link');
            $table->timestamp('paid_at')->nullable()->after('flutterwave_meta');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'flutterwave_tx_ref',
                'flutterwave_transaction_id',
                'flutterwave_payment_link',
                'flutterwave_meta',
                'paid_at',
            ]);
        });
    }
};
