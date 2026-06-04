<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;
use App\Support\ClubManagerAccess;

class WebsitePolicy
{
    public function viewAny(User $authUser): bool
    {
        // Club Managers should not access player WebsiteResource.
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function view(User $authUser, Website $record): bool
    {
        if (ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser)) {
            return true;
        }

        return (int) $record->user_id === (int) $authUser->id;
    }

    public function create(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function update(User $authUser, Website $record): bool
    {
        if (ClubManagerAccess::isClubManager($authUser)) {
            return false;
        }

        if (ClubManagerAccess::isSuperadmin($authUser)) {
            return true;
        }

        return (int) $record->user_id === (int) $authUser->id;
    }

    public function delete(User $authUser, Website $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function deleteAny(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function forceDelete(User $authUser, Website $record): bool { return false; }
    public function forceDeleteAny(User $authUser): bool { return false; }

    public function restore(User $authUser, Website $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function restoreAny(User $authUser): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function replicate(User $authUser, Website $record): bool
    {
        return ClubManagerAccess::isSuperadmin($authUser) && ! ClubManagerAccess::isClubManager($authUser);
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}