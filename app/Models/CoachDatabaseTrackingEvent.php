<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachDatabaseTrackingEvent extends Model
{
    protected $fillable = [
        'athlete_user_id', 'ghl_location_id', 'coach_contact_id', 'school_business_id',
        'campaign_uuid', 'message_uuid', 'template_id', 'event_type', 'platform', 'source',
        'destination_url', 'visitor_hash', 'ip_hash', 'user_agent', 'referer', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
