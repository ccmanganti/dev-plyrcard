<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    protected function isSuperadmin(User $user): bool
    {
        return $user->hasRole('Superadmin')
            || $user->hasRole('superadmin')
            || $user->hasRole('Super Admin');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Schedule $record): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Schedule $record): bool
    {
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by_user_id === (int) $user->id;
    }

    public function delete(User $user, Schedule $record): bool
    {
        return $this->isSuperadmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperadmin($user);
    }

    public function forceDelete(User $user, Schedule $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Schedule $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Schedule $record): bool
    {
        return $this->isSuperadmin($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}