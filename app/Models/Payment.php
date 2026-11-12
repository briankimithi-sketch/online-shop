<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'amount',
        'payment_status',
        'flutterwave_tx_ref',
        'flutterwave_transaction_id',
        'flutterwave_payment_link',
        'flutterwave_meta',
        'mpesa_checkout_request_id',
        'mpesa_merchant_request_id',
        'mpesa_receipt_number',
        'mpesa_phone_number',
        'mpesa_callback_data',
        'mpesa_result_code',
        'mpesa_result_desc',
        'paid_at',
        'inventory_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'flutterwave_meta' => 'array',
            'paid_at' => 'datetime',
            'inventory_restored_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
