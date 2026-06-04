<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClubManagerAccess
{
    public static function isSuperadmin(?User $user): bool
    {
        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function isClubManager(?User $user): bool
    {
        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Club Manager')
                || $user->hasRole('club manager')
                || $user->hasRole('ClubManager')
            );
    }

    public static function canAccessClubArea(?User $user): bool
    {
        return static::isSuperadmin($user) || static::isClubManager($user);
    }

    /**
     * Backward-compatible alias for older leftover resources/clusters.
     */
    public static function canAccessClubAdmin(?User $user): bool
    {
        return static::canAccessClubArea($user);
    }

    /**
     * Club Managers are assigned to exactly one club through users.club_id.
     */
    public static function assignedClubId(?User $user): ?int
    {
        if (! $user || blank($user->club_id)) {
            return null;
        }

        return (int) $user->club_id;
    }

    /**
     * This is the important Club Admin behavior:
     *
     * - If a Superadmin also has the Club Manager role and has club_id set,
     *   the Club Admin area is scoped to that one assigned club.
     * - If a user is Superadmin only, they can see all clubs.
     * - If a user is Club Manager only, they can see only users.club_id.
     */
    public static function clubAdminClubIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $assignedClubId = static::assignedClubId($user);

        if (static::isClubManager($user)) {
            return $assignedClubId ? [$assignedClubId] : [];
        }

        if (static::isSuperadmin($user)) {
            return Club::query()
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    /**
     * Alias used by existing resources.
     */
    public static function managedClubIds(?User $user): array
    {
        return static::clubAdminClubIds($user);
    }

    public static function managedClubs(?User $user): Collection
    {
        $clubIds = static::clubAdminClubIds($user);

        if (empty($clubIds)) {
            return collect();
        }

        return Club::query()
            ->whereIn('id', $clubIds)
            ->orderBy('name')
            ->get();
    }

    public static function userCanAccessClub(?User $user, Club|int|null $club): bool
    {
        if (! $user || ! $club) {
            return false;
        }

        $clubId = $club instanceof Club ? (int) $club->getKey() : (int) $club;

        return in_array($clubId, static::clubAdminClubIds($user), true);
    }

    public static function scopeClubs(Builder $query, ?User $user): Builder
    {
        $clubIds = static::clubAdminClubIds($user);

        return empty($clubIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('id', $clubIds);
    }

    public static function scopePlayers(Builder $query, ?User $user): Builder
    {
        $clubIds = static::clubAdminClubIds($user);

        return empty($clubIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('club_id', $clubIds);
    }

    public static function scopeTeams(Builder $query, ?User $user): Builder
    {
        $clubIds = static::clubAdminClubIds($user);

        return empty($clubIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('club_id', $clubIds);
    }

    public static function canViewPlayer(?User $manager, User $player): bool
    {
        if (! $manager || ! filled($player->club_id)) {
            return false;
        }

        return in_array((int) $player->club_id, static::clubAdminClubIds($manager), true);
    }
}