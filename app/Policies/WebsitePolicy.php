<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewAny(User $authUser): bool
    {
        // Do not expose WebsiteResource to non-superadmins
        return false;
    }

    public function view(User $authUser, Website $record): bool
    {
        return (int) $record->user_id === (int) $authUser->id;
    }

    public function create(User $authUser): bool
    {
        // Allow only through Profile flow if needed
        return true;
    }

    public function update(User $authUser, Website $record): bool
    {
        return (int) $record->user_id === (int) $authUser->id;
    }

    public function delete(User $authUser, Website $record): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, Website $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, Website $record): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, Website $record): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}