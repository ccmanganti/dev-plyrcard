<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseDatasetSyncService;
use App\Services\CoachDatabaseSyncCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncCoachDatabaseDataset extends Command
{
    protected $signature = 'recruiting:sync-dataset
        {--user= : User ID whose Coach Database should be rebuilt}
        {--force : Run even when the command is started manually}
        {--release-lock : Release the shared Recruiting Center lock when finished}';

    protected $description = 'Rebuild the complete school/coach read model in a timeout-safe CLI worker.';

    public function handle(
        CoachDatabaseDatasetSyncService $syncService,
        CoachDatabaseSyncCoordinator $coordinator,
    ): int {
        $userId = (int) $this->option('user');
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if (! $user) {
            $this->error('A valid --user ID is required.');
            return self::FAILURE;
        }

        $sharedLockKey = $coordinator->sharedLockKey($userId);
        $ownsSharedLock = false;

        if (! Cache::has($sharedLockKey)) {
            $ownsSharedLock = Cache::add($sharedLockKey, now()->toDateTimeString(), now()->addHours(3));
        }

        if (! Cache::has($sharedLockKey) && ! $this->option('force')) {
            $this->error('Unable to acquire the Recruiting Center sync lock.');
            return self::FAILURE;
        }

        if (! $coordinator->claimExecution($userId)) {
            $this->warn('Another worker is already processing this Coach Database reload.');
            return self::SUCCESS;
        }

        $coordinator->registerPending($userId);

        try {
            $coordinator->heartbeat($user, [
                'status' => 'running',
                'mode' => 'full_database_reload',
                'worker_started_at' => now()->toDateTimeString(),
                'launch_driver' => 'artisan_command',
                'message' => 'Background worker started. Loading school and coach records in small pages.',
            ]);

            $this->info("Starting timeout-safe Coach Database sync for user {$user->id}...");
            $result = $syncService->sync($user, (bool) $this->option('force'));

            if (! ($result['success'] ?? false)) {
                $this->error((string) ($result['message'] ?? $result['error'] ?? 'Coach Database sync failed.'));
                return self::FAILURE;
            }

            $this->info((string) ($result['message'] ?? 'Coach Database sync completed.'));
            $this->line('Schools: ' . (int) ($result['loaded_schools'] ?? 0));
            $this->line('Coaches: ' . (int) ($result['loaded_contacts'] ?? 0));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            Cache::put($coordinator->statusKey($userId), [
                'status' => 'failed',
                'mode' => 'full_database_reload',
                'user_id' => $userId,
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'Background Coach Database reload failed. The previous cached data was preserved.',
            ], now()->addHours(6));

            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            $coordinator->removePending($userId);
            $coordinator->releaseExecution($userId);

            if ($ownsSharedLock || $this->option('release-lock')) {
                $coordinator->releaseSharedLock($userId);
            }
        }
    }
}
