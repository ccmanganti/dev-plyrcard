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
        {--force : Replace stale locks for a direct/manual run}
        {--release-lock : Release the shared Recruiting Center lock when finished}
        {--launch-driver=artisan_command : Name of the launcher that started this command}
        {--run-token= : Guard token that prevents a late detached process from competing with a fallback worker}';

    protected $description = 'Rebuild the complete school/coach read model in a timeout-safe CLI process.';

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

        $runToken = trim((string) $this->option('run-token'));
        $launchDriver = trim((string) $this->option('launch-driver')) ?: 'artisan_command';
        $statusKey = $coordinator->statusKey($userId);
        $lockKey = $coordinator->sharedLockKey($userId);
        $status = Cache::get($statusKey, []);
        $status = is_array($status) ? $status : [];

        if ($runToken !== '' && ! hash_equals($runToken, (string) ($status['launch_token'] ?? ''))) {
            $this->line('This detached command belongs to an older launch and was ignored safely.');
            return self::SUCCESS;
        }

        // Manual --force can recover a stale local lock. Token-guarded background runs
        // never steal an execution lock from a newer queue/web/scheduler strategy.
        if ((bool) $this->option('force') && $runToken === '') {
            $coordinator->releaseExecution($userId);
            $coordinator->releaseSharedLock($userId);
        }

        if (! $coordinator->claimExecution($userId)) {
            if ($runToken !== '') {
                $this->line('Another verified worker already owns this reload.');
                return self::SUCCESS;
            }

            $this->error('Another Coach Database worker already owns this reload.');
            return self::FAILURE;
        }

        Cache::put($lockKey, now()->toDateTimeString(), now()->addHours(3));
        $coordinator->heartbeat($user, [
            'status' => 'running',
            'mode' => 'full_database_reload',
            'worker_started_at' => now()->toDateTimeString(),
            'launch_driver' => $launchDriver,
            'resolved_driver' => $launchDriver === 'detached_shell' ? 'shell' : $launchDriver,
            'launch_token' => $runToken !== '' ? $runToken : (string) ($status['launch_token'] ?? ''),
            'message' => 'Background command checked in. Loading and reconciling Coach Database records.',
        ]);

        try {
            $this->info("Starting timeout-safe Coach Database sync for user {$user->id}...");
            $result = $syncService->sync($user, true);

            if (! ($result['success'] ?? false)) {
                $this->error((string) ($result['message'] ?? $result['error'] ?? 'Coach Database sync failed.'));
                return self::FAILURE;
            }

            $this->info((string) ($result['message'] ?? 'Coach Database sync completed.'));
            $this->line('Schools: ' . (int) ($result['loaded_schools'] ?? 0));
            $this->line('Coaches: ' . (int) ($result['loaded_contacts'] ?? 0));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $current = Cache::get($statusKey, []);
            $current = is_array($current) ? $current : [];

            // A late stale process must not overwrite the current strategy's status.
            if ($runToken === '' || hash_equals($runToken, (string) ($current['launch_token'] ?? ''))) {
                Cache::put($statusKey, array_merge($current, [
                    'status' => 'failed',
                    'mode' => 'full_database_reload',
                    'user_id' => $user->id,
                    'failed_at' => now()->toDateTimeString(),
                    'error' => $exception->getMessage(),
                    'message' => 'Background Coach Database reload failed. The previous cache was preserved: ' . $exception->getMessage(),
                ]), now()->addHours(6));
            }

            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            $current = Cache::get($statusKey, []);
            $current = is_array($current) ? $current : [];
            $stillOwnsLaunch = $runToken === '' || hash_equals($runToken, (string) ($current['launch_token'] ?? ''));

            if ($stillOwnsLaunch) {
                $coordinator->removePending($userId);
                $coordinator->releaseSharedLock($userId);
                $coordinator->releaseExecution($userId);
                if ((bool) $this->option('release-lock')) {
                    Cache::forget($lockKey);
                }
            }
        }
    }
}