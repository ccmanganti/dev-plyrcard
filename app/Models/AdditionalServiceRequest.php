<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalServiceRequest extends Model
{
    protected $fillable = [
        'user_id',
        'service_key',
        'service_name',
        'listed_price',
        'notes',
        'status',
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
