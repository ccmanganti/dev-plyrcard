<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachDatabaseSchoolMembership extends Model
{
    protected $fillable = [
        'user_id',
        'ghl_location_id',
        'business_id',
        'list_key',
    ];
}
