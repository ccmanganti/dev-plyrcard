<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachDatabaseEmailMessage extends Model
{
    protected $fillable = [
        'athlete_user_id', 'ghl_location_id', 'campaign_uuid', 'message_uuid',
        'ghl_message_id', 'coach_contact_id', 'school_business_id', 'template_id',
        'recipient_email', 'subject', 'rendered_html', 'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];
}
