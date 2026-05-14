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
        'cardholder_name',
        'card_last_four',
        'card_expiration',
        'payment_type',
        'ghl_contact_id',
        'ghl_sync_status',
        'ghl_sync_response',
        'ghl_synced_at',
    ];

    protected $casts = [
        'ghl_sync_response' => 'array',
        'ghl_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
