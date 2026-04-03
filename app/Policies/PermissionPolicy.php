<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Policies\PermissionPolicy;

class PermissionPolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Permission $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Permission $record): bool { return false; }
    public function delete(User $user, Permission $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, Permission $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, Permission $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, Permission $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}