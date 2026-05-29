<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'gender', // legacy single-gender value
        'genders', // new multi-gender value: ["male", "female"]
        'sport',
        'logo',
    ];

    protected $casts = [
        'genders' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Legacy clubs relation. New code should prefer clubLeagues()/clubs().
     */
    public function legacyClubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_leagues')
            ->withPivot(['id', 'genders', 'sport', 'is_active', 'sort_order', 'legacy_club_ids', 'settings', 'deleted_at'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function clubLeagues(): HasMany
    {
        return $this->hasMany(ClubLeague::class);
    }

    public function supportsGender(?string $gender): bool
    {
        $gender = ClubLeague::normalizeGender($gender);

        if (! $gender) {
            return true;
        }

        $genders = collect($this->genders ?? [])
            ->map(fn ($value) => ClubLeague::normalizeGender($value))
            ->filter()
            ->values();

        if ($genders->isEmpty()) {
            $legacyGender = ClubLeague::normalizeGender($this->gender);
            return ! $legacyGender || $legacyGender === $gender || $legacyGender === 'coed';
        }

        return $genders->contains($gender) || $genders->contains('coed');
    }
}