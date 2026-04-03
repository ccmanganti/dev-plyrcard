<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteFieldValue;

class WebsiteFieldValuePolicy
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, WebsiteFieldValue $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, WebsiteFieldValue $record): bool { return false; }
    public function delete(User $user, WebsiteFieldValue $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function forceDelete(User $user, WebsiteFieldValue $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function restore(User $user, WebsiteFieldValue $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, WebsiteFieldValue $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
}