<?php

namespace App\Policies;

use App\Models\HeroTemplate;
use App\Models\User;

class HeroTemplatePolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, HeroTemplate $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, HeroTemplate $record): bool { return false; }
    public function delete(User $user, HeroTemplate $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, HeroTemplate $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, HeroTemplate $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, HeroTemplate $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}