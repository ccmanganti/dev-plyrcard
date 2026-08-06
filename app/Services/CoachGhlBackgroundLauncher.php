<?php

namespace App\Services;

use App\Jobs\ProcessCoachGhlSyncBatch;
use App\Models\CoachGhlSyncRun;

class CoachGhlBackgroundLauncher
{
    /**
     * Dispatch the synchronization to the application's normal Laravel queue.
     *
     * The web request must never start PowerShell, cmd.exe, nohup, or another
     * operating-system process. The application's existing queue worker consumes
     * the default queue and keeps the work alive across page reloads.
     */
    public function launch(CoachGhlSyncRun $run): array
    {
        ProcessCoachGhlSyncBatch::dispatch($run->id)
            ->onConnection('database')
            ->onQueue('default');

        $run->forceFill([
            'status' => 'queued',
            'message' => 'Queued for the application background worker.',
            'heartbeat_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'driver' => 'laravel-database-queue',
            'queue' => 'default',
            'started' => true,
        ];
    }
}