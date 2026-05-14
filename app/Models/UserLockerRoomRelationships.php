<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait UserLockerRoomRelationships
{
    public function billingInformation(): HasOne
    {
        return $this->hasOne(BillingInformation::class);
    }

    public function lockerRoomReferrals(): HasMany
    {
        return $this->hasMany(LockerRoomReferral::class);
    }

    public function lockerRoomSupportRequests(): HasMany
    {
        return $this->hasMany(LockerRoomSupportRequest::class);
    }

    public function additionalServiceRequests(): HasMany
    {
        return $this->hasMany(AdditionalServiceRequest::class);
    }
}
