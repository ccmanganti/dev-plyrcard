<?php

namespace App\Console\Commands;

use App\Services\CoachDatabaseSyncCoordinator;
use App\Services\CoachDatabaseSyncRuntime;
use App\Services\CoachDatabaseWebFallbackSyncService;
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
        CoachDatabaseWebFallbackSyncService $webFallback,
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
            ['App URL', (string) ($diagnostics['app_url'] ?? config('app.url'))],
            ['Local runtime detected', ($diagnostics['local_runtime_detected'] ?? false) ? 'yes' : 'no'],
            ['Configured sync driver', (string) ($diagnostics['configured_driver'] ?? 'auto')],
            ['Resolved sync driver', (string) ($diagnostics['resolved_driver'] ?? 'scheduler')],
            ['Queue connection', (string) ($diagnostics['queue_connection'] ?? 'sync')],
            ['Queue driver', (string) ($diagnostics['queue_driver'] ?? 'unknown')],
            ['Queue is asynchronous', ($diagnostics['queue_is_asynchronous'] ?? false) ? 'yes' : 'no'],
            ['Queue requires check-in', ($diagnostics['queue_requires_worker_checkin'] ?? true) ? 'yes' : 'no'],
            ['Detached shell enabled', ($diagnostics['shell_enabled'] ?? false) ? 'yes' : 'no'],
            ['Shell allowed here', ($diagnostics['shell_allowed_here'] ?? false) ? 'yes' : 'no'],
            ['proc_open available', ($diagnostics['proc_open_available'] ?? false) ? 'yes' : 'no'],
            ['OS family', (string) ($diagnostics['os_family'] ?? PHP_OS_FAMILY)],
            ['Storage logs writable', is_writable(storage_path('logs')) ? 'yes' : 'no'],
            ['Scheduler last seen', $schedulerAge],
            ['Scheduler healthy', ($diagnostics['scheduler_is_healthy'] ?? false) ? 'yes' : 'no'],
            ['Web compatibility fallback', ($diagnostics['web_fallback_enabled'] ?? false) ? 'enabled' : 'disabled'],
            ['Auto strategy order', implode(' -> ', $diagnostics['auto_strategies'] ?? []) ?: 'none'],
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

            $rawStatus = strtolower((string) ($status['status'] ?? ''));
            $heartbeatAt = $status['worker_heartbeat_at'] ?? null;
            $heartbeatTimestamp = $heartbeatAt ? strtotime((string) $heartbeatAt) : false;
            $heartbeatAge = $heartbeatTimestamp ? max(0, time() - $heartbeatTimestamp) : null;
            $staleActive = in_array($rawStatus, ['queued', 'starting', 'waiting_for_worker', 'stalled'], true)
                && ($heartbeatAge === null || $heartbeatAge >= 60);
            $needsCleanup = ($terminal && ($isPending || $sharedLock || $workerLock)) || $staleActive;

            if ($needsCleanup) {
                $this->warn($staleActive
                    ? 'This active-looking sync has no current worker heartbeat.'
                    : 'This completed/failed sync still has stale pending or lock state.');

                if ((bool) $this->option('repair')) {
                    $webFallback->cancel($userId);
                    Cache::put($coordinator->statusKey($userId), array_merge($status, [
                        'status' => 'cleared',
                        'cleared_at' => now()->toDateTimeString(),
                        'message' => 'Stale sync state was cleared by sync-doctor. Existing Coach Database data was not deleted.',
                    ]), now()->addHours(2));
                    $this->info('Cleared the stale pending entry, locks, and compatibility checkpoint.');
                } else {
                    $this->line('Run again with --repair to clear it safely without deleting the Coach Database snapshot.');
                }
            }
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'scheduler' && $schedulerSeen === '') {
            $this->warn('Auto mode resolved to the scheduler, but no scheduler heartbeat has been recorded yet.');
            $this->line('Run `php artisan recruiting:run-pending-dataset-syncs --limit=1` once or configure Laravel schedule:run every minute.');
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'queue') {
            $this->line('Auto mode will try the asynchronous queue first, but it must report a real worker heartbeat. Otherwise the launcher falls back automatically.');
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'shell') {
            $this->line('Auto mode will start a detached CLI process and verify that it checks in.');
        }

        if (($diagnostics['resolved_driver'] ?? null) === 'web_tick') {
            $this->line('Auto mode will use the compatibility worker. Keep a Recruiting Center tab open while a reload is active.');
        }

        return self::SUCCESS;
    }
}