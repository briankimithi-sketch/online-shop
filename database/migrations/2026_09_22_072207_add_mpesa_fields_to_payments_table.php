<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('mpesa_checkout_request_id')->nullable()->after('flutterwave_meta');
            $table->string('mpesa_merchant_request_id')->nullable()->after('mpesa_checkout_request_id');
            $table->string('mpesa_receipt_number')->nullable()->after('mpesa_merchant_request_id');
            $table->string('mpesa_phone_number')->nullable()->after('mpesa_receipt_number');
            $table->text('mpesa_callback_data')->nullable()->after('mpesa_phone_number');
            $table->string('mpesa_result_code')->nullable()->after('mpesa_callback_data');
            $table->string('mpesa_result_desc')->nullable()->after('mpesa_result_code');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_checkout_request_id',
                'mpesa_merchant_request_id',
                'mpesa_receipt_number',
                'mpesa_phone_number',
                'mpesa_callback_data',
                'mpesa_result_code',
                'mpesa_result_desc',
            ]);
        });
    }
};
