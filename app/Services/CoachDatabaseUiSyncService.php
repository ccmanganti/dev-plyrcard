<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CoachDatabaseUiSyncService
{
    public static function cachePrefix(User $user): string
    {
        $datasetKey = 'coach-database:v10:' . $user->id . ':' . Str::slug((string) ($user->ghl_location_id ?: 'default'));

        return 'coach-database:ui:' . $user->id . ':' . md5($datasetKey);
    }

    public static function cacheKey(User $user, string $type, ?string $reference = null): string
    {
        $key = self::cachePrefix($user) . ':' . trim($type);
        if (filled($reference)) {
            $key .= ':' . md5((string) $reference);
        }

        return $key;
    }

    public static function lockKey(User $user, string $type, ?string $reference = null): string
    {
        return 'recruiting:ui-sync-lock:' . $user->id . ':' . trim($type) . ':' . md5((string) ($reference ?? 'all'));
    }

    public static function statusKey(User $user, string $type, ?string $reference = null): string
    {
        return 'recruiting:ui-sync-status:' . $user->id . ':' . trim($type) . ':' . md5((string) ($reference ?? 'all'));
    }

    public static function launchKey(User $user, string $type, ?string $reference = null): string
    {
        return 'recruiting:ui-sync-launch:' . $user->id . ':' . trim($type) . ':' . md5((string) ($reference ?? 'all'));
    }

    public function sync(User $user, string $type, ?string $reference = null, bool $force = false, bool $releaseLock = false): array
    {
        $type = trim(strtolower($type));
        $reference = filled($reference) ? trim((string) $reference) : null;
        $lockKey = self::lockKey($user, $type, $reference);
        $statusKey = self::statusKey($user, $type, $reference);

        if ($force || $releaseLock) {
            Cache::forget($lockKey);
        }

        if (! Cache::add($lockKey, now()->toIso8601String(), now()->addMinutes(10))) {
            return Cache::get($statusKey, [
                'status' => 'already_running',
                'type' => $type,
                'reference' => $reference,
                'message' => 'This data is already refreshing.',
            ]);
        }

        $status = [
            'status' => 'running',
            'type' => $type,
            'reference' => $reference,
            'user_id' => $user->id,
            'started_at' => now()->toIso8601String(),
            'message' => 'Refreshing data.',
        ];
        Cache::put($statusKey, $status, now()->addHours(2));

        try {
            $payload = match ($type) {
                'conversations' => $this->syncConversations($user),
                'messages' => $this->syncMessages($user, (string) $reference),
                'templates' => $this->syncTemplates($user),
                'template-detail' => $this->syncTemplateDetail($user, (string) $reference),
                default => throw new \InvalidArgumentException('Unsupported UI sync type: ' . $type),
            };

            $status = array_merge($status, [
                'status' => 'completed',
                'finished_at' => now()->toIso8601String(),
                'message' => 'Data refreshed.',
                'count' => (int) ($payload['count'] ?? 0),
            ]);
            Cache::put($statusKey, $status, now()->addHours(2));

            return $status;
        } catch (\Throwable $exception) {
            Log::warning('Recruiting Center deferred UI sync failed.', [
                'user_id' => $user->id,
                'type' => $type,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            $status = array_merge($status, [
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'message' => 'The latest data could not refresh. Cached data was kept.',
                'error' => $exception->getMessage(),
            ]);
            Cache::put($statusKey, $status, now()->addHours(2));

            return $status;
        } finally {
            Cache::forget($lockKey);
            Cache::forget(self::launchKey($user, $type, $reference));
        }
    }

    protected function syncConversations(User $user): array
    {
        $result = app(CoachDatabaseService::class)->getConversationsForUser($user, [
            'limit' => min(50, max(10, (int) config('coach-database-sync.ui.conversation_row_cap', 25))),
            'status' => 'all',
        ]);

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Unable to refresh conversations.'));
        }

        $rows = collect($result['conversations'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->take((int) config('coach-database-sync.ui.conversation_row_cap', 25))
            ->values()
            ->all();

        Cache::put(self::cacheKey($user, 'conversations'), [
            'rows' => $rows,
            'cached_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        return ['count' => count($rows)];
    }

    protected function syncMessages(User $user, string $conversationId): array
    {
        if ($conversationId === '') {
            throw new \InvalidArgumentException('A conversation ID is required.');
        }

        $cacheKey = self::cacheKey($user, 'messages', $conversationId);
        $cached = Cache::get($cacheKey, []);
        $cursor = is_array($cached) ? ($cached['last_message_id'] ?? null) : null;

        $result = app(CoachDatabaseService::class)->getConversationMessagesForUser(
            $user,
            $conversationId,
            $cursor,
            min(50, max(10, (int) config('coach-database-sync.ui.message_page_size', 25))),
        );

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Unable to refresh messages.'));
        }

        $existing = is_array($cached['rows'] ?? null) ? $cached['rows'] : [];
        $rows = collect($existing)
            ->merge(is_array($result['messages'] ?? null) ? $result['messages'] : [])
            ->filter(fn ($row): bool => is_array($row))
            ->unique(fn (array $row): string => (string) ($row['id'] ?? md5(json_encode($row))))
            ->sortBy('created_at')
            ->take(-1 * max(25, (int) config('coach-database-sync.ui.message_row_cap', 100)))
            ->values()
            ->all();

        Cache::put($cacheKey, [
            'rows' => $rows,
            'last_message_id' => $result['last_message_id'] ?? $cursor,
            'has_more' => (bool) ($result['has_more'] ?? false),
            'cached_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        return ['count' => count($rows)];
    }

    protected function syncTemplates(User $user): array
    {
        $result = app(CoachDatabaseService::class)->getEmailTemplatesForUser($user);
        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Unable to refresh templates.'));
        }

        $locationId = trim((string) ($user->ghl_location_id ?? config('ghl.location_id') ?? ''));
        $token = trim((string) ($user->ghl_api_key ?? ''));
        $connectionKey = sha1((string) $user->id . '|' . $locationId . '|' . substr(sha1($token), 0, 12));
        $rows = collect($result['templates'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row) use ($connectionKey): array {
                $id = (string) ($row['id'] ?? $row['_id'] ?? $row['templateId'] ?? '');

                return array_merge($row, [
                    'id' => $id,
                    'source_type' => 'ghl',
                    'connection_key' => $connectionKey,
                ]);
            })
            ->filter(fn (array $row): bool => filled($row['id'] ?? null))
            ->unique(fn (array $row): string => (string) $row['id'])
            ->take((int) config('coach-database-sync.ui.template_row_cap', 100))
            ->values()
            ->all();

        $existing = Cache::get(self::cacheKey($user, 'templates'), []);
        Cache::put(self::cacheKey($user, 'templates'), [
            'rows' => $rows,
            'details' => is_array($existing['details'] ?? null) ? $existing['details'] : [],
            'summary' => ! empty($rows)
                ? 'Email templates refreshed. Built-in PLYRCard templates remain available as fallbacks.'
                : 'No saved templates were found. Built-in PLYRCard templates remain available.',
            'debug' => is_array($result['debug'] ?? null) ? $result['debug'] : [],
            'connection_key' => $connectionKey,
            'cached_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        return ['count' => count($rows)];
    }

    protected function syncTemplateDetail(User $user, string $templateId): array
    {
        if ($templateId === '') {
            throw new \InvalidArgumentException('A template ID is required.');
        }

        $result = app(CoachDatabaseService::class)->getEmailTemplateForUser($user, $templateId);
        if (! ($result['success'] ?? false) || ! is_array($result['template'] ?? null)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Unable to refresh the template.'));
        }

        $cacheKey = self::cacheKey($user, 'templates');
        $cached = Cache::get($cacheKey, []);
        $details = is_array($cached['details'] ?? null) ? $cached['details'] : [];
        $details[$templateId] = array_merge($result['template'], [
            'id' => $templateId,
            'source_type' => 'ghl',
            'connection_key' => $cached['connection_key'] ?? null,
        ]);
        $cached['details'] = $details;
        $cached['cached_at'] = now()->toIso8601String();
        Cache::put($cacheKey, $cached, now()->addHours(6));

        return ['count' => 1];
    }
}