<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachDatabaseSchool extends Model
{
    protected $fillable = [
        'user_id',
        'ghl_location_id',
        'business_id',
        'name',
        'logo_url',
        'conference',
        'division',
        'city',
        'state',
        'coach_count',
        'head_coach_name',
        'head_coach_title',
        'head_coach_email',
        'search_text',
        'payload',
        'source_cached_at',
        'last_synced_at',
    ];

    protected $casts = [
        'coach_count' => 'integer',
        'payload' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
