<?php

namespace App\Console\Commands;

use App\Services\CoachDatabaseSyncCoordinator;
use App\Services\CoachDatabaseSyncRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CoachDatabaseSyncDoctor extends Command
{
    protected $signature = 'recruiting:sync-doctor
        {--user= : Optional user ID to inspect}
        {--repair : Remove stale pending entries for completed/failed users}';

    protected $description = 'Inspect the environment-independent Coach Database background-worker configuration.';

    public function handle(
        CoachDatabaseSyncCoordinator $coordinator,
        CoachDatabaseSyncRuntime $runtime,
    ): int {
        $diagnostics = $runtime->diagnostics();
        $schedulerHeartbeat = $coordinator->schedulerHeartbeat();
        $schedulerSeen = (string) ($schedulerHeartbeat['at'] ?? '');
        $schedulerAge = 'never';

        if ($schedulerSeen !== '') {
            try {
                $schedulerAge = Carbon::parse($schedulerSeen)->diffForHumans();
            } catch (\Throwable) {
                $schedulerAge = $schedulerSeen;
            }
        }

        $this->table(['Check', 'Value'], [
            ['App environment', (string) ($diagnostics['app_environment'] ?? app()->environment())],
            ['Configured sync driver', (string) ($diagnostics['configured_driver'] ?? 'auto')],
            ['Resolved sync driver', (string) ($diagnostics['resolved_driver'] ?? 'scheduler')],
            ['Queue connection', (string) ($diagnostics['queue_connection'] ?? 'sync')],
            ['Queue driver', (string) ($diagnostics['queue_driver'] ?? 'unknown')],
            ['Queue is asynchronous', ($diagnostics['queue_is_asynchronous'] ?? false) ? 'yes' : 'no'],
            ['Detached shell enabled', ($diagnostics['shell_enabled'] ?? false) ? 'yes' : 'no'],
            ['proc_open available', ($diagnostics['proc_open_available'] ?? false) ? 'yes' : 'no'],
            ['OS family', (string) ($diagnostics['os_family'] ?? PHP_OS_FAMILY)],
            ['Storage logs writable', is_writable(storage_path('logs')) ? 'yes' : 'no'],
            ['Scheduler last seen', $schedulerAge],
            ['Pending user IDs', implode(', ', $coordinator->pendingUsers()) ?: 'none'],
        ]);

        $userId = (int) $this->option('user');
        if ($userId > 0) {
            $status = Cache::get($coordinator->statusKey($userId), []);
            $status = is_array($status) ? $status : [];

            $this->newLine();
            $this->line("Status for user {$userId}:");
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            $sharedLock = Cache::has($coordinator->sharedLockKey($userId));
            $workerLock = Cache::has($coordinator->executionLockKey($userId));
            $isPending = in_array($userId, $coordinator->pendingUsers(), true);
            $terminal = in_array((string) ($status['status'] ?? ''), ['completed', 'failed', 'cleared'], true);

            $this->line('Shared lock: ' . ($sharedLock ? 'present' : 'absent'));
            $this->line('Worker lock: ' . ($workerLock ? 'present' : 'absent'));

            if ($isPending && $terminal && ! $sharedLock && ! $workerLock) {
                $this->warn('The pending registry is stale for this completed/failed sync.');
                if ((bool) $this->option('repair')) {
                    $coordinator->removePending($userId);
                    $this->info('Removed the stale pending registry entry.');
                } else {
                    $this->line('Run again with --repair to remove it safely.');
                }
            }
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'scheduler' && $schedulerSeen === '') {
            $this->warn('Auto mode resolved to the scheduler, but no scheduler heartbeat has been recorded yet.');
            $this->line('Run `php artisan recruiting:run-pending-dataset-syncs --limit=1` once or configure Laravel schedule:run every minute.');
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'queue') {
            $this->line('Auto mode will use the asynchronous queue first. Keep the recruiting queue worker running.');
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'shell') {
            $this->line('Auto mode will start a detached CLI process. No environment-specific driver change is required.');
        }

        return self::SUCCESS;
    }
}
