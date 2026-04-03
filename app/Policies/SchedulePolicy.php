<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Schedule $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Schedule $record): bool { return false; }
    public function delete(User $user, Schedule $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, Schedule $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, Schedule $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, Schedule $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}