<?php

namespace App\Support;

use App\Models\Club;
use App\Models\TeamManagerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClubManagerAccess
{
    public static function isSuperadmin(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) ($user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            ));
    }

    public static function isClubManager(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) ($user && method_exists($user, 'hasRole') && $user->hasRole('Club Manager'));
    }

    public static function isTeamManager(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) ($user && method_exists($user, 'hasRole') && $user->hasRole('Team Manager'));
    }

    public static function isCoachManager(?User $user = null): bool
    {
        return static::isClubManager($user) || static::isTeamManager($user);
    }

    public static function canAccessClubArea(?User $user = null): bool
    {
        $user ??= auth()->user();

        return static::isSuperadmin($user)
            || static::isClubManager($user)
            || static::isTeamManager($user);
    }

    public static function assignedClubId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        return filled($user->club_id ?? null) ? (int) $user->club_id : null;
    }

    public static function assignedClub(?User $user = null): ?Club
    {
        $clubId = static::assignedClubId($user);

        return $clubId ? Club::query()->withoutGlobalScopes()->find($clubId) : null;
    }

    public static function managedClubs(?User $user = null)
    {
        $user ??= auth()->user();

        if (! $user) {
            return Club::query()->whereRaw('1 = 0');
        }

        if (static::isSuperadmin($user)) {
            return Club::query()->withoutGlobalScopes();
        }

        $clubId = static::assignedClubId($user);

        return Club::query()
            ->withoutGlobalScopes()
            ->when($clubId, fn (Builder $query) => $query->whereKey($clubId), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function userCanAccessClub(?User $user, ?Club $club): bool
    {
        if (! $user || ! $club) {
            return false;
        }

        if (static::isSuperadmin($user)) {
            return true;
        }

        return static::canAccessClubArea($user)
            && static::assignedClubId($user) === (int) $club->getKey();
    }

    public static function teamManagerAssignments(?User $user = null)
    {
        $user ??= auth()->user();

        if (! $user || ! static::isTeamManager($user)) {
            return collect();
        }

        return TeamManagerAssignment::query()
            ->with(['clubLeague.league'])
            ->where('user_id', $user->id)
            ->when(static::assignedClubId($user), fn (Builder $query, int $clubId) => $query->where('club_id', $clubId))
            ->get();
    }

    public static function allowedTeamNames(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! static::isTeamManager($user)) {
            return [];
        }

        return static::teamManagerAssignments($user)
            ->pluck('team_name')
            ->filter()
            ->map(fn ($team) => strtoupper(trim((string) $team)))
            ->unique()
            ->values()
            ->all();
    }

    public static function allowedClubLeagueIds(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! static::isTeamManager($user)) {
            return [];
        }

        return static::teamManagerAssignments($user)
            ->pluck('club_league_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function allowedLeagueIds(?User $user = null): array
    {
        $user ??= auth()->user();

        if (! static::isTeamManager($user)) {
            return [];
        }

        return static::teamManagerAssignments($user)
            ->pluck('league_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function teamManagerCanAccessTeam(?User $user, ?int $clubLeagueId, ?int $leagueId, ?string $teamName): bool
    {
        if (! static::isTeamManager($user)) {
            return true;
        }

        $teamName = strtoupper(trim((string) $teamName));

        if (blank($teamName)) {
            return false;
        }

        return static::teamManagerAssignments($user)
            ->contains(function (TeamManagerAssignment $assignment) use ($clubLeagueId, $leagueId, $teamName): bool {
                $assignmentTeam = strtoupper(trim((string) $assignment->team_name));

                if ($assignmentTeam !== $teamName) {
                    return false;
                }

                if ($clubLeagueId && (int) $assignment->club_league_id === (int) $clubLeagueId) {
                    return true;
                }

                if ($leagueId && (int) $assignment->league_id === (int) $leagueId) {
                    return true;
                }

                return false;
            });
    }

    public static function scopePlayers(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (static::isSuperadmin($user)) {
            return $query;
        }

        $clubId = static::assignedClubId($user);

        if (! $clubId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('club_id', $clubId);

        if (static::isTeamManager($user)) {
            $teams = static::allowedTeamNames($user);
            $clubLeagueIds = static::allowedClubLeagueIds($user);
            $leagueIds = static::allowedLeagueIds($user);

            if (empty($teams)) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('team_name', $teams);

            if (! empty($clubLeagueIds) && SchemaFacade::hasColumn('users', 'club_league_id')) {
                $query->where(function (Builder $query) use ($clubLeagueIds, $leagueIds): void {
                    $query->whereIn('club_league_id', $clubLeagueIds);

                    if (! empty($leagueIds) && SchemaFacade::hasColumn('users', 'league_id')) {
                        $query->orWhere(function (Builder $query) use ($leagueIds): void {
                            $query->whereNull('club_league_id')
                                ->whereIn('league_id', $leagueIds);
                        });
                    }
                });
            } elseif (! empty($leagueIds) && SchemaFacade::hasColumn('users', 'league_id')) {
                $query->whereIn('league_id', $leagueIds);
            }
        }

        return $query;
    }

    public static function canViewPlayer(?User $manager, ?User $player): bool
    {
        if (! $manager || ! $player) {
            return false;
        }

        if (static::isSuperadmin($manager)) {
            return true;
        }

        if (! static::canAccessClubArea($manager)) {
            return false;
        }

        if (static::assignedClubId($manager) !== (int) ($player->club_id ?? 0)) {
            return false;
        }

        if (static::isClubManager($manager)) {
            return true;
        }

        if (! static::isTeamManager($manager)) {
            return false;
        }

        return static::teamManagerCanAccessTeam(
            $manager,
            $player->club_league_id ?? null,
            $player->league_id ?? null,
            $player->team_name ?? null,
        );
    }

    public static function playerDisplayName(User $player): string
    {
        return trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? ''))
            ?: ($player->name ?? $player->email ?? 'Player');
    }

    public static function playerInitials(User $player): string
    {
        $name = static::playerDisplayName($player);

        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn ($part) => Str::substr((string) $part, 0, 1))
            ->take(2)
            ->implode('')
            ?: 'P';
    }

    public static function playerEmail(User $player): ?string
    {
        return $player->email ?? $player->personal_email ?? null;
    }

    public static function playerPhone(User $player): ?string
    {
        return $player->phone ?? $player->parent_phone ?? null;
    }

    public static function playerWebsiteUrl(User $player): ?string
    {
        if (method_exists($player, 'websites')) {
            $website = $player->relationLoaded('websites')
                ? $player->websites->first()
                : $player->websites()->first();

            if ($website?->domain) {
                return str_starts_with($website->domain, 'http')
                    ? $website->domain
                    : 'https://' . $website->domain;
            }
        }

        return null;
    }

    public static function playerPlyrCardImageUrl(User $player): ?string
    {
        return static::normalizeImageUrl(static::playerPlyrCardImageValue($player));
    }

    public static function playerProfileImageUrl(User $player): ?string
    {
        return static::normalizeImageUrl(static::playerProfileImageValue($player));
    }

    public static function playerImageUrl(User $player): ?string
    {
        return static::playerPlyrCardImageUrl($player) ?: static::playerProfileImageUrl($player);
    }

    protected static function playerPlyrCardImageValue(User $player): mixed
    {
        foreach ([
            'plyrcard_image',
            'player_card_image',
            'plyr_card_image',
            'card_image',
            'generated_card_image',
            'generated_card',
            'share_card_image',
        ] as $field) {
            if (filled($player->{$field} ?? null)) {
                return $player->{$field};
            }
        }

        return null;
    }

    protected static function playerProfileImageValue(User $player): mixed
    {
        foreach ([
            'player_image',
            'profile_photo_path',
            'avatar',
            'photo',
            'headshot',
            'profile_image',
            'image',
            'action_image',
            'mobile_hero_image',
            'raw_player_images',
        ] as $field) {
            if (filled($player->{$field} ?? null)) {
                return $player->{$field};
            }
        }

        return null;
    }

    protected static function normalizeImageUrl(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->filter()->first();
        }

        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $value = ltrim(str_replace('public/', '', $value), '/');

        return Storage::disk('public')->url($value);
    }
}