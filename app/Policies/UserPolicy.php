<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        // Allow all logged-in role types into the Profile resource/page
        return $authUser->hasAnyRole([
            'Superadmin',
            'Rookie',
            'Rookie Plus',
            'My Journey',
        ]);
    }

    public function view(User $authUser, User $record): bool
    {
        return $authUser->id === $record->id;
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, User $record): bool
    {
        return $authUser->id === $record->id;
    }

    public function delete(User $authUser, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, User $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, User $record): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, User $record): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}