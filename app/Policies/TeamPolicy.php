<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;


class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function restore(User $user, Team $team): bool
    {
        return $user->hasRole('Superadmin');
    }

    public function forceDelete(User $user, Team $team): bool
    {
        return $user->hasRole('Superadmin');
    }
}