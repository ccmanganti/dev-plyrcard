<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseTagSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncCoachDatabaseTags extends Command
{
    protected $signature = 'recruiting:sync-tags
        {--user= : User ID whose Favorites/Saved/List tags should be refreshed}
        {--force : Run even when started manually}
        {--release-lock : Release the tag-sync lock when finished}';

    protected $description = 'Refresh Recruiting Center Favorites, Saved items, and list tags outside Livewire.';

    public function handle(CoachDatabaseTagSyncService $syncService): int
    {
        $userId = (int) $this->option('user');
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if (! $user) {
            $this->error('A valid --user ID is required.');
            return self::FAILURE;
        }

        $lockKey = 'recruiting:tag-sync-running:' . $user->id;
        $ownsLock = false;

        if (! Cache::has($lockKey)) {
            $ownsLock = Cache::add($lockKey, now()->toDateTimeString(), now()->addMinutes(30));
        }

        if (! Cache::has($lockKey) && ! $this->option('force')) {
            $this->error('Unable to acquire the Recruiting Center tag-sync lock.');
            return self::FAILURE;
        }

        try {
            $result = $syncService->sync($user, (bool) $this->option('force'));
            $message = (string) ($result['message'] ?? 'Tag sync finished.');

            if (! ($result['success'] ?? false)) {
                $this->error($message);
                return self::FAILURE;
            }

            $this->info($message);
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            if ($ownsLock || $this->option('release-lock')) {
                Cache::forget($lockKey);
            }
        }
    }
}
