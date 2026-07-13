<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Starts the Coach Database reload with one predictable strategy everywhere.
 *
 * No queue worker, detached shell, Supervisor, or cron is required. The page
 * processes small checkpointed API pages during passive Livewire polling while
 * the previous cache stays available to the user.
 */
class CoachDatabaseBackgroundSyncLauncher
{
    public function __construct(
        protected CoachDatabaseSyncCoordinator $coordinator,
        protected CoachDatabaseWebFallbackSyncService $incrementalSync,
    ) {}

    /** @param array<string, mixed> $baseStatus */
    public function launchDataset(User $user, array $baseStatus): array
    {
        $userId = (int) $user->id;
        $this->coordinator->registerPending($userId);

        $status = Arr::except($baseStatus, [
            'worker_heartbeat_at',
            'worker_started_at',
            'worker_host',
            'worker_pid',
            'finished_at',
            'failed_at',
            'error',
            'warnings',
            'launch_token',
        ]);

        return $this->incrementalSync->start($user, array_merge($status, [
            'configured_driver' => 'incremental_livewire',
            'resolved_driver' => 'incremental_livewire',
            'launch_driver' => 'incremental_livewire',
            'launch_token' => (string) Str::uuid(),
            'message' => 'Background reload started. Small checkpointed pages will load while the Recruiting Center remains usable.',
        ]), true);
    }
}