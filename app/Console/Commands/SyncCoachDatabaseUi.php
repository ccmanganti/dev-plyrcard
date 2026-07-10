<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseUiSyncService;
use Illuminate\Console\Command;

class SyncCoachDatabaseUi extends Command
{
    protected $signature = 'recruiting:sync-ui
        {--user= : User ID}
        {--type= : conversations, messages, templates, or template-detail}
        {--reference= : Conversation or template ID when required}
        {--force : Clear an existing lock first}
        {--release-lock : Release a stale lock before running}';

    protected $description = 'Refresh lightweight Recruiting Center UI data outside Livewire requests.';

    public function handle(CoachDatabaseUiSyncService $service): int
    {
        $userId = (int) $this->option('user');
        $type = trim((string) $this->option('type'));
        $reference = filled($this->option('reference')) ? (string) $this->option('reference') : null;

        $user = User::query()->find($userId);
        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $result = $service->sync(
            $user,
            $type,
            $reference,
            (bool) $this->option('force'),
            (bool) $this->option('release-lock'),
        );

        $status = (string) ($result['status'] ?? 'unknown');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $status === 'completed' || $status === 'already_running'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
