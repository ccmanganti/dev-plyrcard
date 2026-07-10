<?php

namespace App\Services;

use App\Jobs\SyncCoachDatabaseDatasetJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class CoachDatabaseBackgroundSyncLauncher
{
    public function __construct(
        protected CoachDatabaseSyncCoordinator $coordinator,
        protected CoachDatabaseSyncRuntime $runtime,
    ) {}

    /** @param array<string, mixed> $baseStatus */
    public function launchDataset(User $user, array $baseStatus): array
    {
        $userId = (int) $user->id;
        $this->coordinator->registerPending($userId);

        $requestedDriver = $this->runtime->configuredDriver();
        $baseStatus = array_merge($baseStatus, [
            'configured_driver' => $requestedDriver,
            'resolved_driver' => $requestedDriver === 'auto'
                ? $this->runtime->resolvedAutoDriver()
                : $requestedDriver,
        ]);

        $strategies = $this->strategiesFor($requestedDriver);

        foreach ($strategies as $strategy) {
            $status = match ($strategy) {
                'queue' => $this->tryQueue($user, $baseStatus),
                'shell' => $this->tryDetachedShell($user, $baseStatus),
                default => null,
            };

            if ($status !== null) {
                return $status;
            }
        }

        return $this->waitingForScheduledWorker($user, $baseStatus);
    }

    /** @return array<int, string> */
    protected function strategiesFor(string $requestedDriver): array
    {
        if ($requestedDriver === 'auto') {
            return $this->runtime->autoStrategies();
        }

        // Explicit modes still fail over safely unless fallback is disabled.
        $fallback = (bool) config('coach-database-sync.background.allow_fallback', true);

        return match ($requestedDriver) {
            'queue' => $fallback ? ['queue', 'shell'] : ['queue'],
            'shell' => $fallback ? ['shell', 'queue'] : ['shell'],
            'scheduler' => [],
            default => ['queue', 'shell'],
        };
    }

    /** @param array<string, mixed> $baseStatus */
    protected function tryQueue(User $user, array $baseStatus): ?array
    {
        if (! (bool) config('coach-database-sync.background.queue_enabled', true)) {
            return null;
        }

        if (! $this->runtime->queueIsAsynchronous()) {
            return null;
        }

        $connection = $this->runtime->queueConnection();
        $queue = (string) config('coach-database-sync.background.queue_name', 'recruiting');

        $status = array_merge($baseStatus, [
            'status' => 'queued',
            'launch_driver' => 'queue',
            'resolved_driver' => 'queue',
            'queue_connection' => $connection,
            'queue_name' => $queue,
            'queued_at' => now()->toDateTimeString(),
            'message' => 'Coach Database reload queued. Existing rows remain available while the worker starts.',
        ]);
        Cache::put($this->coordinator->statusKey((int) $user->id), $status, now()->addHours(6));

        try {
            SyncCoachDatabaseDatasetJob::dispatch((int) $user->id)
                ->onConnection($connection)
                ->onQueue($queue);

            return $status;
        } catch (Throwable $exception) {
            Log::warning('Unable to queue Coach Database dataset sync; trying another launcher.', [
                'user_id' => $user->id,
                'queue_connection' => $connection,
                'queue_driver' => $this->runtime->queueDriver(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @param array<string, mixed> $baseStatus */
    protected function tryDetachedShell(User $user, array $baseStatus): ?array
    {
        if (! $this->runtime->canSpawnDetachedProcess()) {
            return null;
        }

        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $userId = (int) $user->id;
        $logPath = storage_path("logs/recruiting-dataset-sync-{$userId}.log");
        $arguments = ' --user=' . $userId
            . ' --force --release-lock --launch-driver=detached_shell';

        $status = array_merge($baseStatus, [
            'status' => 'starting',
            'launch_driver' => 'detached_shell',
            'resolved_driver' => 'shell',
            'launch_attempted_at' => now()->toDateTimeString(),
            'launcher_host' => gethostname() ?: php_uname('n'),
            'launcher_pid' => getmypid(),
            'worker_log' => $logPath,
            'message' => 'Coach Database reload is starting in a detached background process. Existing rows remain available.',
        ]);
        Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));

        try {
            $pid = null;

            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'cmd /C start "" /B '
                    . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-dataset' . $arguments
                    . ' > ' . escapeshellarg($logPath) . ' 2>&1';

                $process = Process::fromShellCommandline($command, base_path());
                $process->setTimeout(10)->run();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Windows detached process could not be started.');
                }
            } else {
                $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-dataset' . $arguments
                    . ' > ' . escapeshellarg($logPath) . ' 2>&1 < /dev/null & echo $!';

                $process = Process::fromShellCommandline($command, base_path());
                $process->setTimeout(10)->run();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Detached process could not be started.');
                }

                $pidValue = trim($process->getOutput());
                $pid = ctype_digit($pidValue) ? (int) $pidValue : null;
            }

            $current = Cache::get($this->coordinator->statusKey($userId), []);
            $current = is_array($current) ? $current : [];

            // Do not overwrite a fast child process that has already reported `running`.
            if (in_array((string) ($current['status'] ?? ''), ['running', 'completed', 'failed'], true)) {
                return $current;
            }

            $status['detached_pid'] = $pid;
            Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));

            return $status;
        } catch (Throwable $exception) {
            Log::warning('Detached Coach Database process could not be started; scheduled fallback remains pending.', [
                'user_id' => $user->id,
                'os_family' => PHP_OS_FAMILY,
                'php_binary' => $php,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @param array<string, mixed> $baseStatus */
    protected function waitingForScheduledWorker(User $user, array $baseStatus): array
    {
        $heartbeat = $this->coordinator->schedulerHeartbeat();
        $lastSeen = $heartbeat['at'] ?? null;

        $message = $lastSeen
            ? 'Coach Database reload is waiting for the scheduled worker. The scheduler was last seen at ' . $lastSeen . '.'
            : 'Coach Database reload is waiting for the scheduled worker. Existing rows remain available.';

        $status = array_merge($baseStatus, [
            'status' => 'waiting_for_worker',
            'launch_driver' => 'scheduler',
            'resolved_driver' => 'scheduler',
            'scheduler_last_seen_at' => $lastSeen,
            'waiting_since' => now()->toDateTimeString(),
            'message' => $message,
        ]);
        Cache::put($this->coordinator->statusKey((int) $user->id), $status, now()->addHours(6));

        return $status;
    }
}
