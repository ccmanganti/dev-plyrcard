<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasName, FilamentUser
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'personal_email',
        'email',
        'phone',
        'country',
        'state',
        'city',
        'street',
        'gpa',
        'year',
        'birth',
        'height',
        'weight',
        'jersey_number',
        'sport',
        'position',
        'accolades',
        'natl_team_exp',
        'team_name',
        'ig_handle',
        'x_handle',
        'yt_url',
        'press',
        'parent',
        'parent_email',
        'parent_phone',
        'sec_parent',
        'sec_parent_email',
        'sec_parent_phone',
        'club_coach',
        'club_coach_email',
        'club_coach_phone',
        'natl_coach',
        'natl_coach_email',
        'natl_coach_phone',
        'tech_trainer',
        'tech_trainer_email',
        'tech_trainer_phone',
        'snc_trainer',
        'snc_trainer_email',
        'snc_trainer_phone',
        'school_id',
        'club_id',
        'domain',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'natl_team_exp' => 'boolean',
            'position' => 'array',
        ];
    }

    public function getFilamentName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function activeWebsite(): HasOne
    {
        return $this->hasOne(Website::class)->where('is_active', true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}