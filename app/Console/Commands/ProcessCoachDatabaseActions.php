<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseActionQueueService;
use Illuminate\Console\Command;

class ProcessCoachDatabaseActions extends Command
{
    protected $signature = 'recruiting:process-actions {--user= : User ID}';
    protected $description = 'Process checkpointed Recruiting Center background actions.';

    public function handle(CoachDatabaseActionQueueService $queue): int
    {
        $userId = (int) $this->option('user');
        $user = User::query()->find($userId);

        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $result = $queue->process($user);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['status'] ?? '') === 'failed'
            ? self::FAILURE
            : self::SUCCESS;
    }
}