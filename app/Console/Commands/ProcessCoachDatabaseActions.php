<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseActionQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessCoachDatabaseActions extends Command
{
    protected $signature = 'recruiting:process-actions {--user=} {--release-lock}';

    protected $description = 'Process queued Recruiting Center contact and list actions outside Livewire.';

    public function handle(CoachDatabaseActionQueueService $service): int
    {
        $userId = (int) $this->option('user');
        $user = User::query()->find($userId);

        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        if ($this->option('release-lock')) {
            Cache::forget(CoachDatabaseActionQueueService::workerLockKey($user));
            Cache::forget(CoachDatabaseActionQueueService::launchKey($user));
        }

        $result = $service->process($user);
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
