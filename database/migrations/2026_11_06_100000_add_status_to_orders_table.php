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
            $table->string('status', 20)->default('pending')->index();
        });

        // Preserve the payment state of existing orders.
        DB::table('orders')
            ->join('payments', 'orders.id', '=', 'payments.order_id')
            ->where('payments.payment_status', 'paid')
            ->update(['orders.status' => 'paid']);

        DB::table('orders')
            ->join('payments', 'orders.id', '=', 'payments.order_id')
            ->where('payments.payment_status', 'failed')
            ->update(['orders.status' => 'failed']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
