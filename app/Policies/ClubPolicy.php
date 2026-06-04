<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubManagerAccess;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return ClubManagerAccess::canAccessClubArea($user);
    }

    public function view(User $user, Club $record): bool
    {
        return ClubManagerAccess::userCanAccessClub($user, $record);
    }

    public function create(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function update(User $user, Club $record): bool
    {
        // Club Managers may update landing page content for their assigned club only.
        // Keep the Club Landing Page resource limited to landing-page fields.
        return ClubManagerAccess::userCanAccessClub($user, $record);
    }

    public function delete(User $user, Club $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function forceDelete(User $user, Club $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    public function restore(User $user, Club $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function restoreAny(User $user): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function replicate(User $user, Club $record): bool
    {
        return ClubManagerAccess::isSuperadmin($user) && ! ClubManagerAccess::isClubManager($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}