<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, School $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, School $record): bool { return false; }
    public function delete(User $user, School $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, School $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, School $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, School $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}