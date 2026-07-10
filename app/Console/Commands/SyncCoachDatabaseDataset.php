<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseDatasetSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncCoachDatabaseDataset extends Command
{
    protected $signature = 'recruiting:sync-dataset
        {--user= : User ID whose GHL Coach Database should be rebuilt}
        {--force : Run even when the command is started manually}
        {--release-lock : Release the shared Recruiting Center lock when finished}';

    protected $description = 'Rebuild the complete GHL school/coach read model in a timeout-safe background CLI process.';

    public function handle(CoachDatabaseDatasetSyncService $syncService): int
    {
        $userId = (int) $this->option('user');
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if (! $user) {
            $this->error('A valid --user ID is required.');
            return self::FAILURE;
        }

        $lockKey = 'recruiting:stats-sync-running:' . $user->id;
        $statusKey = 'recruiting:stats-sync-status:' . $user->id;
        $ownsLock = false;

        if (! Cache::has($lockKey)) {
            $ownsLock = Cache::add($lockKey, now()->toDateTimeString(), now()->addMinutes(90));
        }

        if (! Cache::has($lockKey) && ! $this->option('force')) {
            $this->error('Unable to acquire the Recruiting Center sync lock.');
            return self::FAILURE;
        }

        try {
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
            Cache::put($statusKey, [
                'status' => 'failed',
                'mode' => 'full_database_reload',
                'user_id' => $user->id,
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'Background Coach Database reload failed. The previous cache was preserved: ' . $exception->getMessage(),
            ], now()->addMinutes(120));

            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            if ($ownsLock || $this->option('release-lock')) {
                Cache::forget($lockKey);
            }
        }
    }
}
