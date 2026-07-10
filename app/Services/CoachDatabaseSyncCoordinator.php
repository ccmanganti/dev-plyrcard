<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CoachDatabaseSyncCoordinator
{
    public const PENDING_USERS_KEY = 'recruiting:dataset-sync-pending-users';

    public function sharedLockKey(int $userId): string
    {
        return 'recruiting:stats-sync-running:' . $userId;
    }

    public function statusKey(int $userId): string
    {
        return 'recruiting:stats-sync-status:' . $userId;
    }

    public function executionLockKey(int $userId): string
    {
        return 'recruiting:dataset-sync-worker:' . $userId;
    }

    public function registerPending(int $userId): void
    {
        $this->mutatePendingUsers(function (array $ids) use ($userId): array {
            $ids[] = $userId;
            return array_values(array_unique(array_map('intval', $ids)));
        });
    }

    public function removePending(int $userId): void
    {
        $this->mutatePendingUsers(
            fn (array $ids): array => array_values(array_filter(
                array_map('intval', $ids),
                fn (int $id): bool => $id !== $userId,
            )),
        );
    }

    /** @return array<int, int> */
    public function pendingUsers(): array
    {
        $ids = Cache::get(self::PENDING_USERS_KEY, []);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        )));
    }

    public function claimExecution(int $userId, int $minutes = 180): bool
    {
        return Cache::add(
            $this->executionLockKey($userId),
            [
                'claimed_at' => now()->toDateTimeString(),
                'host' => gethostname() ?: php_uname('n'),
                'pid' => getmypid(),
            ],
            now()->addMinutes(max(5, $minutes)),
        );
    }

    public function releaseExecution(int $userId): void
    {
        Cache::forget($this->executionLockKey($userId));
    }

    public function heartbeat(User|int $user, array $extra = []): array
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $statusKey = $this->statusKey($userId);
        $status = Cache::get($statusKey, []);
        $status = is_array($status) ? $status : [];

        $status = array_merge($status, $extra, [
            'user_id' => $userId,
            'worker_heartbeat_at' => now()->toDateTimeString(),
            'worker_host' => gethostname() ?: php_uname('n'),
            'worker_pid' => getmypid(),
        ]);

        Cache::put($statusKey, $status, now()->addHours(6));
        Cache::put($this->sharedLockKey($userId), $status['started_at'] ?? now()->toDateTimeString(), now()->addHours(3));

        return $status;
    }

    public function releaseSharedLock(int $userId): void
    {
        Cache::forget($this->sharedLockKey($userId));
    }

    public function markFailedToStart(int $userId, string $message, ?Throwable $exception = null): array
    {
        $status = Cache::get($this->statusKey($userId), []);
        $status = is_array($status) ? $status : [];
        $status = array_merge($status, [
            'status' => 'waiting_for_worker',
            'mode' => 'full_database_reload',
            'user_id' => $userId,
            'progress' => max(1, (int) ($status['progress'] ?? 1)),
            'waiting_since' => now()->toDateTimeString(),
            'message' => $message,
        ]);

        if ($exception) {
            $status['launch_error'] = $exception->getMessage();
        }

        Cache::put($this->statusKey($userId), $status, now()->addHours(6));

        Log::warning('Coach Database background worker is waiting to be started.', [
            'user_id' => $userId,
            'message' => $message,
            'error' => $exception?->getMessage(),
        ]);

        return $status;
    }

    protected function mutatePendingUsers(callable $callback): void
    {
        try {
            Cache::lock(self::PENDING_USERS_KEY . ':lock', 10)->block(3, function () use ($callback): void {
                $ids = Cache::get(self::PENDING_USERS_KEY, []);
                $ids = is_array($ids) ? $ids : [];
                Cache::put(self::PENDING_USERS_KEY, $callback($ids), now()->addDays(2));
            });
        } catch (Throwable $exception) {
            // Last-resort fallback for cache drivers that do not support atomic locks.
            $ids = Cache::get(self::PENDING_USERS_KEY, []);
            $ids = is_array($ids) ? $ids : [];
            Cache::put(self::PENDING_USERS_KEY, $callback($ids), now()->addDays(2));

            Log::debug('Pending Coach Database sync registry used non-locking fallback.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
