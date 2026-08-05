<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class School extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'state',
        'city',
        'street',
        'zipcode',
        'logo_path',
        'website_url',
        'ghl_business_id',
        'ghl_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'ghl_synced_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function coaches(): HasMany
    {
        return $this->hasMany(Coach::class);
    }

    /**
     * A school can have a different GHL business ID in every subaccount.
     * This mapping is authoritative; ghl_business_id remains a compatibility value.
     */
    public function ghlSyncTargets(): HasMany
    {
        return $this->hasMany(SchoolGhlSyncTarget::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}