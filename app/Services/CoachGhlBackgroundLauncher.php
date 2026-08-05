<?php

namespace App\Services;

use App\Jobs\ProcessCoachGhlSyncBatch;
use App\Models\CoachGhlSyncRun;
use Illuminate\Support\Facades\Process;
use Throwable;

class CoachGhlBackgroundLauncher
{
    public function launch(CoachGhlSyncRun $run): array
    {
        ProcessCoachGhlSyncBatch::dispatch($run->id);

        try {
            $php = escapeshellarg(PHP_BINARY);
            $artisan = escapeshellarg(base_path('artisan'));
            $command = "{$php} {$artisan} queue:work --queue=coach-ghl-sync --stop-when-empty --sleep=0 --tries=3 --timeout=300";

            if (PHP_OS_FAMILY === 'Windows') {
                Process::path(base_path())->start('cmd /c start "" /B ' . $command);
            } else {
                Process::path(base_path())->start('nohup ' . $command . ' > storage/logs/coach-ghl-sync.log 2>&1 &');
            }

            return ['driver' => 'database-detached-worker', 'started' => true];
        } catch (Throwable $exception) {
            report($exception);
            return ['driver' => 'database-queued', 'started' => false, 'error' => $exception->getMessage()];
        }
    }
}