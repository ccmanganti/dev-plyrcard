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
    ) {}

    public function launchDataset(User $user, array $baseStatus): array
    {
        $userId = (int) $user->id;
        $this->coordinator->registerPending($userId);

        $requestedDriver = strtolower(trim((string) config('coach-database-sync.background.driver', 'auto')));
        if (! in_array($requestedDriver, ['auto', 'queue', 'shell', 'scheduler'], true)) {
            $requestedDriver = 'auto';
        }

        if (in_array($requestedDriver, ['auto', 'queue'], true)) {
            $queued = $this->tryQueue($user, $baseStatus);
            if ($queued !== null) {
                return $queued;
            }
        }

        if (in_array($requestedDriver, ['auto', 'shell'], true)) {
            $launched = $this->tryDetachedShell($user, $baseStatus);
            if ($launched !== null) {
                return $launched;
            }
        }

        return $this->waitingForScheduledWorker($user, $baseStatus);
    }

    protected function tryQueue(User $user, array $baseStatus): ?array
    {
        if (! (bool) config('coach-database-sync.background.queue_enabled', true)) {
            return null;
        }

        $configuredConnection = config('coach-database-sync.background.queue_connection');
        $connection = filled($configuredConnection)
            ? (string) $configuredConnection
            : (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connection}.driver", '');

        // A sync queue would execute the full reload inside the current Livewire request.
        if ($connection === '' || $driver === '' || $driver === 'sync') {
            return null;
        }

        $queue = (string) config('coach-database-sync.background.queue_name', 'recruiting');

        try {
            SyncCoachDatabaseDatasetJob::dispatch((int) $user->id)
                ->onConnection($connection)
                ->onQueue($queue);

            $status = array_merge($baseStatus, [
                'status' => 'queued',
                'launch_driver' => 'queue',
                'queue_connection' => $connection,
                'queue_name' => $queue,
                'queued_at' => now()->toDateTimeString(),
                'message' => 'Full Coach Database reload is queued for background processing. Existing rows remain visible while the worker starts.',
            ]);
            Cache::put($this->coordinator->statusKey((int) $user->id), $status, now()->addHours(6));

            return $status;
        } catch (Throwable $exception) {
            Log::warning('Unable to queue Coach Database dataset sync; trying another launcher.', [
                'user_id' => $user->id,
                'queue_connection' => $connection,
                'queue_driver' => $driver,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function tryDetachedShell(User $user, array $baseStatus): ?array
    {
        if (! (bool) config('coach-database-sync.background.shell_enabled', true)) {
            return null;
        }

        if (! $this->functionAvailable('proc_open')) {
            return null;
        }

        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/recruiting-dataset-sync-' . (int) $user->id . '.log');
        $arguments = ' --user=' . (int) $user->id . ' --force --release-lock';

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-dataset' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1';
                $handle = popen($command, 'r');
                if (is_resource($handle)) {
                    pclose($handle);
                }
            } else {
                $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
                    . ' recruiting:sync-dataset' . $arguments . ' > ' . escapeshellarg($logPath) . ' 2>&1 < /dev/null &';
                Process::fromShellCommandline($command, base_path())
                    ->setTimeout(10)
                    ->run();
            }

            $status = array_merge($baseStatus, [
                'status' => 'starting',
                'launch_driver' => 'detached_shell',
                'launch_attempted_at' => now()->toDateTimeString(),
                'worker_log' => $logPath,
                'message' => 'Full Coach Database reload is starting in a background worker. Existing rows remain visible.',
            ]);
            Cache::put($this->coordinator->statusKey((int) $user->id), $status, now()->addHours(6));

            return $status;
        } catch (Throwable $exception) {
            Log::warning('Detached Coach Database process could not be started; scheduled fallback remains pending.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function waitingForScheduledWorker(User $user, array $baseStatus): array
    {
        $status = array_merge($baseStatus, [
            'status' => 'waiting_for_worker',
            'launch_driver' => 'scheduler',
            'waiting_since' => now()->toDateTimeString(),
            'message' => 'Full Coach Database reload is waiting for the server background worker. Existing rows remain visible. The scheduled worker will pick it up automatically.',
        ]);
        Cache::put($this->coordinator->statusKey((int) $user->id), $status, now()->addHours(6));

        return $status;
    }

    protected function functionAvailable(string $function): bool
    {
        if (! function_exists($function)) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return ! in_array($function, $disabled, true);
    }
}
