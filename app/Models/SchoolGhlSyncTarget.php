<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolGhlSyncTarget extends Model
{
    protected $fillable = [
        'school_id',
        'representative_user_id',
        'api_key_hash',
        'location_id',
        'normalized_name',
        'ghl_business_id',
        'status',
        'last_action',
        'last_error',
        'checked_at',
        'synced_at',
        'last_counted_run_id',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function representativeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }
}
