<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Schedule extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'opponent',
        'game_date',
        'game_time',
        'location',
        'venue',
        'status',
        'is_home',
        'result',
        'score',
        'notes',
    ];

    protected $casts = [
        'game_date' => 'date',
        'game_time' => 'datetime:H:i',
        'is_home' => 'boolean',
    ];

    // Personal schedule creation is intentionally owner-only. Team schedules must
    // be shared explicitly by a club administrator via syncToTeamMembers(), not
    // automatically whenever any athlete adds an event.

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function syncToTeamMembers(int $clubId, ?int $leagueId = null, ?string $teamName = null): void
    {
        $players = User::query()
            ->where('club_id', $clubId)
            ->when($leagueId, fn ($query) => $query->where('league_id', $leagueId))
            ->when(filled($teamName), fn ($query) => $query->where('team_name', $teamName))
            ->pluck('id');

        if ($players->isEmpty()) {
            return;
        }

        $this->users()->syncWithoutDetaching($players->all());
    }
}