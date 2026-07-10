<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CoachDatabaseDatasetSyncService;
use App\Services\CoachDatabaseSyncCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncCoachDatabaseDatasetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;
    public bool $failOnTimeout = true;

    public function __construct(public int $userId) {}

    public function handle(
        CoachDatabaseDatasetSyncService $syncService,
        CoachDatabaseSyncCoordinator $coordinator,
    ): void {
        if (
            ! in_array($this->userId, $coordinator->pendingUsers(), true)
            && ! Cache::has($coordinator->sharedLockKey($this->userId))
        ) {
            return;
        }

        if (! $coordinator->claimExecution($this->userId)) {
            return;
        }

        $user = User::query()->find($this->userId);
        if (! $user) {
            $coordinator->removePending($this->userId);
            $coordinator->releaseSharedLock($this->userId);
            $coordinator->releaseExecution($this->userId);
            return;
        }

        try {
            $coordinator->heartbeat($user, [
                'status' => 'running',
                'mode' => 'full_database_reload',
                'worker_started_at' => now()->toDateTimeString(),
                'launch_driver' => 'queue',
                'resolved_driver' => 'queue',
                'message' => 'Queue worker started. Loading school and coach records in small pages.',
            ]);

            $result = $syncService->sync($user, true);
            if (! ($result['success'] ?? false)) {
                throw new RuntimeException((string) ($result['message'] ?? $result['error'] ?? 'Coach Database reload failed.'));
            }
        } finally {
            $coordinator->removePending($this->userId);
            $coordinator->releaseSharedLock($this->userId);
            $coordinator->releaseExecution($this->userId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $coordinator = app(CoachDatabaseSyncCoordinator::class);
        $statusKey = $coordinator->statusKey($this->userId);
        $status = Cache::get($statusKey, []);
        $status = is_array($status) ? $status : [];

        Cache::put($statusKey, array_merge($status, [
            'status' => 'failed',
            'mode' => 'full_database_reload',
            'user_id' => $this->userId,
            'failed_at' => now()->toDateTimeString(),
            'error' => $exception?->getMessage(),
            'message' => 'Background Coach Database reload failed. The previous cached data remains available.',
        ]), now()->addHours(6));

        $coordinator->removePending($this->userId);
        $coordinator->releaseSharedLock($this->userId);
        $coordinator->releaseExecution($this->userId);

        Log::error('Queued Coach Database reload failed.', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
