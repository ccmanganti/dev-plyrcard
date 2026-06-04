<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClubReferral extends Model
{
    protected $fillable = [
        'token',
        'club_manager_id',
        'club_id',
        'league_id',
        'club_league_id',
        'team_name',
        'sport',
        'gender',
        'invited_email',
        'invited_name',
        'invite_url',
        'status',
        'clicked_at',
        'registered_at',
        'registered_user_id',
        'utm_payload',
        'notes',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'registered_at' => 'datetime',
        'utm_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClubReferral $referral) {
            if (blank($referral->token)) {
                do {
                    $token = Str::lower(Str::random(12));
                } while (static::query()->where('token', $token)->exists());

                $referral->token = $token;
            }

            if (blank($referral->status)) {
                $referral->status = 'active';
            }
        });

        static::saved(function (ClubReferral $referral) {
            if (blank($referral->invite_url) && filled($referral->token)) {
                $referral->forceFill([
                    'invite_url' => $referral->registrationUrl(),
                ])->saveQuietly();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function clubManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'club_manager_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function clubLeague(): BelongsTo
    {
        return $this->belongsTo(ClubLeague::class);
    }

    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    public function registrationUrl(): string
    {
        $clubName = $this->club?->name;
        $leagueName = $this->league?->name;

        return url('/registration?' . http_build_query([
            'utm_plan' => 'free',
            'utm_source' => 'club_referral',
            'utm_medium' => 'club_admin',
            'utm_campaign' => 'club_invite',
            'utm_referral' => $this->token,
            'club_referral_token' => $this->token,
            'utm_club_id' => $this->club_id,
            'utm_club' => $clubName,
            'utm_league_id' => $this->league_id,
            'utm_league' => $leagueName,
            'utm_club_league_id' => $this->club_league_id,
            'utm_team_name' => $this->team_name,
            'utm_sport' => $this->sport,
            'utm_gender' => $this->gender,
        ]));
    }

    public function markClicked(): void
    {
        if (! $this->clicked_at) {
            $this->forceFill([
                'clicked_at' => now(),
                'status' => $this->status === 'active' ? 'clicked' : $this->status,
            ])->save();
        }
    }

    public function markRegistered(User $user): void
    {
        $this->forceFill([
            'registered_user_id' => $user->id,
            'registered_at' => now(),
            'status' => 'registered',
        ])->save();
    }
} 