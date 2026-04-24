<?php

namespace App\Observers;

use App\Jobs\SyncProfileCompletionToGhl;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function saved(User $user): void
    {
        $completion = app(ProfileCompletionService::class)->calculate($user);

        $user->forceFill([
            'profile_completion_percentage' => $completion,
        ])->saveQuietly();

        if (
            $completion >= 75 &&
            is_null($user->profile_completion_threshold_sent_at)
        ) {
            SyncProfileCompletionToGhl::dispatch($user->id, $completion);
        }
    }
}