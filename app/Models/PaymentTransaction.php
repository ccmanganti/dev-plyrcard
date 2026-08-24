<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'billing_information_id',
        'plan_key',
        'ghl_location_id',
        'ghl_contact_id',
        'ghl_transaction_id',
        'ghl_order_id',
        'ghl_subscription_id',
        'ghl_charge_id',
        'status',
        'currency',
        'amount_cents',
        'refunded_amount_cents',
        'payment_provider',
        'payment_mode',
        'live_mode',
        'card_brand',
        'card_last_four',
        'entity_type',
        'entity_id',
        'source_type',
        'source_sub_type',
        'source_name',
        'ghl_created_at',
        'ghl_updated_at',
        'paid_at',
        'synced_at',
        'ghl_payload',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'refunded_amount_cents' => 'integer',
        'live_mode' => 'boolean',
        'ghl_created_at' => 'datetime',
        'ghl_updated_at' => 'datetime',
        'paid_at' => 'datetime',
        'synced_at' => 'datetime',
        'ghl_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingInformation(): BelongsTo
    {
        return $this->belongsTo(BillingInformation::class);
    }
}
