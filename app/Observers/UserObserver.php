<?php

namespace App\Observers;

use App\Jobs\SyncProfileCompletionToGhl;
use App\Models\User;
use App\Services\ProfileCompletionService;
use App\Services\SupportAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function created(User $user): void
    {
        $userId = (int) $user->getKey();

        DB::afterCommit(function () use ($userId): void {
            $createdUser = User::query()->find($userId);

            if (! $createdUser) {
                return;
            }

            try {
                app(SupportAlertService::class)->sendNewRegistration($createdUser);
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD new user admin alert failed.', [
                    'user_id' => $userId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function saved(User $user): void
    {
        $completion = app(ProfileCompletionService::class)->calculate($user);

        $user->forceFill([
            'profile_completion_percentage' => $completion,
        ])->saveQuietly();

        if ($completion >= 75 && is_null($user->profile_completion_threshold_sent_at)) {
            SyncProfileCompletionToGhl::dispatch($user->id, $completion);
        }
    }
}