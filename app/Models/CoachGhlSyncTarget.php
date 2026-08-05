<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachGhlSyncTarget extends Model
{
    protected $fillable = [
        'coach_id',
        'representative_user_id',
        'api_key_hash',
        'location_id',
        'account_user_ids',
        'school_name_snapshot',
        'coach_email_snapshot',
        'ghl_contact_id',
        'ghl_business_id',
        'status',
        'matched_by',
        'last_error',
        'checked_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'account_user_ids' => 'array',
            'checked_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function representativeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }
}
