<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteHeroFieldValue;

class WebsiteHeroFieldValuePolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function delete(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, WebsiteHeroFieldValue $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}