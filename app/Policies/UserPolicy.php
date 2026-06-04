<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ClubManagerAccess;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole([
            'Superadmin',
            'superadmin',
            'Super Admin',
            'Rookie',
            'Rookie Plus',
            'My Journey',
            'Club Manager',
        ]);
    }

    public function view(User $authUser, User $record): bool
    {
        if (ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser)) {
            return true;
        }

        if ($authUser->id === $record->id) {
            return true;
        }

        return ClubManagerAccess::canViewPlayer($authUser, $record);
    }

    public function create(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser);
    }

    public function update(User $authUser, User $record): bool
    {
        if (ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser)) {
            return true;
        }

        // Club admins/managers must never modify player data.
        if (ClubManagerAccess::canViewPlayer($authUser, $record)) {
            return false;
        }

        return $authUser->id === $record->id;
    }

    public function delete(User $authUser, User $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function deleteAny(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function forceDelete(User $authUser, User $record): bool { return false; }
    public function forceDeleteAny(User $authUser): bool { return false; }

    public function restore(User $authUser, User $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function restoreAny(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function replicate(User $authUser, User $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}