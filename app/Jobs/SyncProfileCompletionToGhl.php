<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GoHighLevelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProfileCompletionToGhl implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $completion
    ) {}

    public function handle(GoHighLevelService $ghl): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        if ($ghl->syncProfileCompletion($user, $this->completion)) {
            $user->forceFill([
                'profile_completion_percentage' => $this->completion,
                'profile_completion_threshold_sent_at' => now(),
            ])->saveQuietly();
        }
    }
}