<?php

namespace App\Policies;

use App\Models\NationalTeam;
use App\Models\User;

class NationalTeamPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, NationalTeam $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, NationalTeam $record): bool { return false; }
    public function delete(User $user, NationalTeam $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, NationalTeam $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, NationalTeam $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, NationalTeam $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}