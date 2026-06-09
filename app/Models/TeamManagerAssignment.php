<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamManagerAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'club_id',
        'club_league_id',
        'league_id',
        'team_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function clubLeague(): BelongsTo
    {
        return $this->belongsTo(ClubLeague::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}