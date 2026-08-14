<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInformation extends Model
{
    protected $fillable = [
        'user_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',

        // Display-only payment metadata. Never store a full PAN or CVC here.
        'cardholder_name',
        'card_last_four',
        'card_expiration',
        'payment_type',
        'payment_brand',
        'payment_provider',

        // Native registration / commercial context.
        'plan_key',
        'billing_cycle',
        'currency',
        'recurring_amount_cents',
        'setup_fee_cents',
        'initial_amount_cents',
        'payment_status',
        'subscription_status',
        'requested_domain',
        'requested_handle',
        'registration_meta',

        // HighLevel identifiers and sync state.
        'ghl_contact_id',
        'ghl_location_id',
        'ghl_invoice_id',
        'ghl_invoice_schedule_id',
        'ghl_subscription_id',
        'ghl_transaction_id',
        'ghl_payment_method_id',
        'ghl_customer_id',
        'ghl_sync_status',
        'ghl_sync_response',
        'ghl_last_webhook_id',
        'ghl_synced_at',
        'ghl_payment_completed_at',
        'ghl_last_event_at',
    ];

    protected $casts = [
        'recurring_amount_cents' => 'integer',
        'setup_fee_cents' => 'integer',
        'initial_amount_cents' => 'integer',
        'registration_meta' => 'array',
        'ghl_sync_response' => 'array',
        'ghl_synced_at' => 'datetime',
        'ghl_payment_completed_at' => 'datetime',
        'ghl_last_event_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}