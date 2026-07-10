<?php

namespace App\Console\Commands;

use App\Services\CoachDatabaseSyncCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CoachDatabaseSyncDoctor extends Command
{
    protected $signature = 'recruiting:sync-doctor {--user= : Optional user ID to inspect}';

    protected $description = 'Inspect the production background-worker configuration for Coach Database reloads.';

    public function handle(CoachDatabaseSyncCoordinator $coordinator): int
    {
        $connection = (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connection}.driver", 'unknown');
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $procOpen = function_exists('proc_open') && ! in_array('proc_open', $disabled, true);

        $this->table(['Check', 'Value'], [
            ['App environment', (string) app()->environment()],
            ['Configured sync driver', (string) config('coach-database-sync.background.driver', 'auto')],
            ['Queue connection', $connection],
            ['Queue driver', $driver],
            ['Queue is asynchronous', $driver !== 'sync' ? 'yes' : 'no'],
            ['proc_open available', $procOpen ? 'yes' : 'no'],
            ['Storage logs writable', is_writable(storage_path('logs')) ? 'yes' : 'no'],
            ['Pending user IDs', implode(', ', $coordinator->pendingUsers()) ?: 'none'],
        ]);

        $userId = (int) $this->option('user');
        if ($userId > 0) {
            $status = Cache::get($coordinator->statusKey($userId), []);
            $status = is_array($status) ? $status : [];

            $this->newLine();
            $this->line("Status for user {$userId}:");
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
            $this->line('Shared lock: ' . (Cache::has($coordinator->sharedLockKey($userId)) ? 'present' : 'absent'));
            $this->line('Worker lock: ' . (Cache::has($coordinator->executionLockKey($userId)) ? 'present' : 'absent'));
        }

        if ($driver === 'sync' && ! $procOpen) {
            $this->warn('No asynchronous queue or detached process is available. Configure the scheduled fallback command every minute.');
        }

        return self::SUCCESS;
    }
}
