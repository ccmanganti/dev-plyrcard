<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseDatasetSyncService;
use App\Services\CoachDatabaseSyncCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunPendingCoachDatabaseDatasetSyncs extends Command
{
    protected $signature = 'recruiting:run-pending-dataset-syncs
        {--limit=1 : Maximum pending users to process during this invocation}';

    protected $description = 'Process pending Coach Database reloads when queue or detached processes are unavailable.';

    public function handle(
        CoachDatabaseDatasetSyncService $syncService,
        CoachDatabaseSyncCoordinator $coordinator,
    ): int {
        $coordinator->recordSchedulerHeartbeat();
        $coordinator->cleanTerminalPendingEntries();

        $limit = max(1, min(20, (int) $this->option('limit')));
        $pending = array_slice($coordinator->pendingUsers(), 0, $limit);

        if ($pending === []) {
            $this->line('No pending Coach Database reloads.');
            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($pending as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                $coordinator->removePending($userId);
                $coordinator->releaseSharedLock($userId);
                continue;
            }

            if (! $coordinator->claimExecution($userId)) {
                $this->line("User {$userId}: another worker already owns the reload.");
                continue;
            }

            try {
                $coordinator->heartbeat($user, [
                    'status' => 'running',
                    'mode' => 'full_database_reload',
                    'worker_started_at' => now()->toDateTimeString(),
                    'launch_driver' => 'scheduled_command',
                    'resolved_driver' => 'scheduler',
                    'message' => 'Scheduled background worker started. Loading school and coach records in small pages.',
                ]);

                $this->info("User {$userId}: starting pending Coach Database reload...");
                $result = $syncService->sync($user, true);

                if (! ($result['success'] ?? false)) {
                    $failed++;
                    $this->error("User {$userId}: " . (string) ($result['message'] ?? $result['error'] ?? 'reload failed'));
                } else {
                    $this->info("User {$userId}: reload completed.");
                }
            } catch (Throwable $exception) {
                $failed++;
                $statusKey = $coordinator->statusKey($userId);
                $status = Cache::get($statusKey, []);
                $status = is_array($status) ? $status : [];

                Cache::put($statusKey, array_merge($status, [
                    'status' => 'failed',
                    'mode' => 'full_database_reload',
                    'user_id' => $userId,
                    'failed_at' => now()->toDateTimeString(),
                    'error' => $exception->getMessage(),
                    'message' => 'Scheduled Coach Database reload failed. The previous cached data remains available.',
                ]), now()->addHours(6));

                $this->error("User {$userId}: {$exception->getMessage()}");
            } finally {
                $coordinator->removePending($userId);
                $coordinator->releaseSharedLock($userId);
                $coordinator->releaseExecution($userId);
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
