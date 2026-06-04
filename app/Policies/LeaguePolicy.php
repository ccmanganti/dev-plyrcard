<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;
use App\Support\ClubManagerAccess;

class LeaguePolicy
{
    public function viewAny(User $user): bool
    {
        return ClubManagerAccess::canAccessClubArea($user);
    }

    public function view(User $user, League $record): bool
    {
        if (ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user)) {
            return true;
        }

        $clubIds = ClubManagerAccess::clubAdminClubIds($user);

        if (empty($clubIds)) {
            return false;
        }

        return $record->clubLeagues()
            ->whereIn('club_id', $clubIds)
            ->where('is_active', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function update(User $user, League $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function delete(User $user, League $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function forceDelete(User $user, League $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    public function restore(User $user, League $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function restoreAny(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function replicate(User $user, League $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}