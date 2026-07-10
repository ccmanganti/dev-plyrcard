<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CoachDatabaseTagSyncService
{
    public function __construct(
        protected CoachDatabaseService $coachDatabaseService,
    ) {}

    public function sync(User $user, bool $force = false): array
    {
        $this->prepareCliRuntime();

        $cacheKey = $this->coachDatabaseService->recruitingSnapshotCacheKey($user);
        $statusKey = $this->statusKey($user);
        $existing = Cache::get($cacheKey, []);
        $existing = is_array($existing) ? $existing : [];
        $startedAt = now()->toDateTimeString();

        $this->writeStatus($user, [
            'status' => 'running',
            'mode' => 'contact_tag_sync',
            'started_at' => $startedAt,
            'message' => 'Refreshing Favorites, Saved items, and custom-list tags in the background.',
        ]);

        try {
            $customListTags = collect($existing['custom_list_tags'] ?? [])
                ->map(function ($item): ?string {
                    if (is_array($item)) {
                        $value = $item['tag'] ?? $item['name'] ?? $item['value'] ?? null;
                        return is_scalar($value) ? trim((string) $value) : null;
                    }
                    return is_scalar($item) ? trim((string) $item) : null;
                })
                ->filter(fn (?string $tag): bool => filled($tag))
                ->values()
                ->all();

            $tags = $this->coachDatabaseService->actionTags($user, $customListTags);
            if (empty($tags)) {
                throw new RuntimeException('No Recruiting Center action tags are configured.');
            }

            $result = $this->coachDatabaseService->getContactsByTagsForUser($user, $tags);
            $incoming = collect($result['contacts'] ?? [])
                ->filter(fn ($coach): bool => is_array($coach))
                ->values()
                ->all();

            $byTag = is_array($result['by_tag'] ?? null) ? $result['by_tag'] : [];
            $allQueriesSucceeded = ! empty($byTag) && collect($byTag)
                ->every(fn ($row): bool => is_array($row) && (bool) ($row['success'] ?? false));

            if (! $allQueriesSucceeded && empty($incoming)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Unable to refresh contact tags from GHL.'));
            }

            $existingCoaches = collect($existing['coaches'] ?? [])
                ->filter(fn ($coach): bool => is_array($coach))
                ->values()
                ->all();

            // Only clear known action/list tags when every tag query succeeded. On a
            // partial GHL outage, preserve the previous flags and merge whatever rows
            // were returned instead of making Favorites suddenly disappear.
            if ($allQueriesSucceeded) {
                $actionTagLookup = collect($tags)
                    ->mapWithKeys(fn ($tag): array => [strtolower(trim((string) $tag)) => true])
                    ->all();

                $existingCoaches = collect($existingCoaches)
                    ->map(function (array $coach) use ($actionTagLookup): array {
                        $coach['tags'] = collect($coach['tags'] ?? [])
                            ->map(fn ($tag): string => trim((string) $tag))
                            ->filter(fn (string $tag): bool => $tag !== '' && ! isset($actionTagLookup[strtolower($tag)]))
                            ->values()
                            ->all();

                        foreach (['is_saved_school', 'is_favorite_school', 'is_saved_coach', 'is_favorite_coach'] as $flag) {
                            $coach[$flag] = false;
                        }
                        return $coach;
                    })
                    ->all();
            }

            $mergedCoaches = $this->mergeCoachRows($existingCoaches, $incoming);
            $rebuilt = $this->coachDatabaseService->rebuildFromSchoolCompanySnapshot(
                schools: is_array($existing['schools'] ?? null) ? $existing['schools'] : [],
                coaches: $mergedCoaches,
                user: $user,
                customListTags: $customListTags,
            );

            $finishedAt = now()->toDateTimeString();
            $final = array_merge($existing, $rebuilt, [
                'tag_synced_at' => $finishedAt,
                'tag_sync_started_at' => $startedAt,
                'tag_sync_finished_at' => $finishedAt,
                'tag_sync_mode' => 'background_by_tag',
                'tag_sync_partial' => ! $allQueriesSucceeded,
                'last_tag_sync_count' => count($incoming),
                'last_tag_sync_debug' => $byTag ?: ($result['debug'] ?? []),
                'cached_at' => $finishedAt,
            ]);

            Cache::put(
                $cacheKey,
                $final,
                now()->addHours((int) config('ghl.coach_database.cache_hours', 12)),
            );

            $status = [
                'status' => $allQueriesSucceeded ? 'completed' : 'completed_partial',
                'mode' => 'contact_tag_sync',
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'contacts' => count($incoming),
                'message' => $allQueriesSucceeded
                    ? 'Favorites, Saved items, and custom lists were refreshed from GHL.'
                    : 'Tag refresh completed partially. Existing cached tag data was preserved for failed GHL queries.',
            ];
            $this->writeStatus($user, $status);

            return array_merge(['success' => true], $status);
        } catch (Throwable $exception) {
            Log::warning('Recruiting Center background tag sync failed safely.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            $status = [
                'status' => 'failed',
                'mode' => 'contact_tag_sync',
                'started_at' => $startedAt,
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'Favorites/List refresh failed, but the previous cached data was preserved: ' . $exception->getMessage(),
            ];
            $this->writeStatus($user, $status);

            return array_merge(['success' => false], $status);
        }
    }

    protected function mergeCoachRows(array $existing, array $incoming): array
    {
        $rows = [];

        foreach ($existing as $coach) {
            if (! is_array($coach)) {
                continue;
            }
            $rows[$this->identity($coach)] = $coach;
        }

        foreach ($incoming as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            $key = $this->identity($coach);
            $previous = $rows[$key] ?? [];
            $merged = array_replace($previous, array_filter($coach, fn ($value): bool => $value !== null && $value !== ''));
            $merged['tags'] = collect($previous['tags'] ?? [])
                ->merge($coach['tags'] ?? [])
                ->map(fn ($tag): string => trim((string) $tag))
                ->filter()
                ->unique(fn (string $tag): string => strtolower($tag))
                ->values()
                ->all();
            $rows[$key] = $merged;
        }

        return array_values($rows);
    }

    protected function identity(array $coach): string
    {
        foreach (['id', 'contact_id', 'contactId'] as $field) {
            $value = strtolower(trim((string) ($coach[$field] ?? '')));
            if ($value !== '') {
                return 'id:' . $value;
            }
        }

        $email = strtolower(trim((string) ($coach['email'] ?? '')));
        if ($email !== '') {
            return 'email:' . $email;
        }

        return 'fallback:' . sha1(json_encode($coach));
    }

    protected function prepareCliRuntime(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Recruiting Center tag sync must run from CLI/background processing.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', (string) config('coach-database-sync.cli_memory_limit', '512M'));
    }

    protected function writeStatus(User $user, array $status): void
    {
        $status['user_id'] = $user->id;
        Cache::put($this->statusKey($user), $status, now()->addMinutes(60));
    }

    protected function statusKey(User $user): string
    {
        return 'recruiting:tag-sync-status:' . $user->id;
    }
}
