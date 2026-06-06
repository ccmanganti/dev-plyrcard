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

    protected static function booted(): void
    {
        static::created(function (Schedule $schedule): void {
            /*
             * Default safety behavior:
             * When a schedule is created by a player/user that already has
             * club_id + league_id + team_name, attach the same schedule to the
             * rest of that team.
             *
             * Club Admin dashboard creation calls syncToTeamMembers() directly
             * because a Club Manager's own account may not have a league/team.
             */
            $creator = $schedule->creator;

            if (! $creator || blank($creator->club_id) || blank($creator->league_id) || blank($creator->team_name)) {
                return;
            }

            $schedule->syncToTeamMembers(
                clubId: (int) $creator->club_id,
                leagueId: (int) $creator->league_id,
                teamName: (string) $creator->team_name,
            );
        });
    }

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