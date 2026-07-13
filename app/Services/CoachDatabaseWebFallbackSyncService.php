<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CoachDatabaseWebFallbackSyncService
{
    public function __construct(
        protected CoachDatabaseService $coachDatabaseService,
        protected CoachDatabaseSyncCoordinator $coordinator,
    ) {}

    public function start(User $user, array $baseStatus = [], bool $force = false): array
    {
        $userId = (int) $user->id;
        $checkpoint = $this->readCheckpoint($userId);

        if ($force || $checkpoint === []) {
            $checkpoint = $this->newCheckpoint($user);
            $this->writeCheckpoint($userId, $checkpoint);
        }

        if (! Cache::has($this->coordinator->executionLockKey($userId))) {
            $this->coordinator->claimExecution($userId, 180);
        }

        $status = array_merge($baseStatus, [
            'status' => 'running',
            'mode' => 'full_database_reload',
            'launch_driver' => 'incremental_livewire',
            'resolved_driver' => 'incremental_livewire',
            'progress' => max(1, (int) ($checkpoint['progress'] ?? 1)),
            'loaded_schools' => count($checkpoint['schools'] ?? []),
            'loaded_contacts' => count($checkpoint['coaches'] ?? []),
            'loaded_pages' => (int) ($checkpoint['business_pages'] ?? 0) + (int) ($checkpoint['contact_pages'] ?? 0),
            'worker_started_at' => $checkpoint['started_at'] ?? now()->toDateTimeString(),
            'worker_heartbeat_at' => now()->toDateTimeString(),
            'message' => 'Background reload started. Small checkpointed pages will be processed while this Recruiting Center page remains open.',
        ]);

        Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));
        Cache::put($this->coordinator->sharedLockKey($userId), $status['worker_started_at'], now()->addHours(3));

        return $status;
    }

    /**
     * Process at most one remote page per request. This is the last-resort runner used
     * when a server has no asynchronous queue, no healthy scheduler, and cannot keep a
     * detached PHP process alive. A cache lock prevents multiple browser tabs from doing
     * the same page concurrently.
     */
    public function cancel(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $this->deleteCheckpoint($userId);
        Cache::forget($this->tickLockKey($userId));
        $this->coordinator->removePending($userId);
        $this->coordinator->releaseSharedLock($userId);
        $this->coordinator->releaseExecution($userId);
    }

    public function tick(User $user): array
    {
        $userId = (int) $user->id;
        $lock = Cache::lock($this->tickLockKey($userId), max(10, (int) config('coach-database-sync.incremental.tick_lock_seconds', 30)));

        if (! $lock->get()) {
            return $this->currentStatus($userId);
        }

        try {
            $checkpoint = $this->readCheckpoint($userId);
            if ($checkpoint === []) {
                $this->start($user, [], true);
                $checkpoint = $this->readCheckpoint($userId);
            }

            $retryNotBefore = $checkpoint['retry_not_before'] ?? null;
            if ($retryNotBefore && strtotime((string) $retryNotBefore) > time()) {
                $checkpoint['progress'] = $this->calculateProgress($checkpoint);
                $checkpoint['updated_at'] = now()->toDateTimeString();
                $this->writeCheckpoint($userId, $checkpoint);
                return $this->writeRunningStatus($user, $checkpoint);
            }

            $phase = (string) ($checkpoint['phase'] ?? 'fetch');

            if ($phase === 'finalize') {
                return $this->finalize($user, $checkpoint);
            }

            if ($phase === 'completed') {
                return $this->currentStatus($userId);
            }

            $this->applyBoundedHttpTimeouts();

            // The incremental browser worker
            // processes a bounded burst while staying below the web request timeout.
            $maxPages = max(1, min(5, (int) config('coach-database-sync.incremental.pages_per_tick', 2)));
            $timeBudget = max(4, min(20, (int) config('coach-database-sync.incremental.time_budget_seconds', 12)));
            $finalizeReserve = max(1, min(5, (int) config('coach-database-sync.incremental.finalize_reserve_seconds', 2)));
            $started = microtime(true);
            $processedThisTick = 0;

            while ($processedThisTick < $maxPages) {
                $phase = (string) ($checkpoint['phase'] ?? 'fetch');
                if ($phase === 'finalize') {
                    if ((microtime(true) - $started) <= max(1, $timeBudget - $finalizeReserve)) {
                        return $this->finalize($user, $checkpoint);
                    }
                    break;
                }

                $source = $this->nextSource($checkpoint);
                if ($source === 'businesses') {
                    $checkpoint = $this->processBusinessPage($user, $checkpoint);
                } elseif ($source === 'contacts') {
                    $checkpoint = $this->processContactPage($user, $checkpoint);
                } else {
                    $checkpoint['phase'] = 'finalize';
                    continue;
                }

                $processedThisTick++;

                if (! ($checkpoint['business_has_more'] ?? false) && ! ($checkpoint['contacts_have_more'] ?? false)) {
                    $checkpoint['phase'] = 'finalize';
                }

                if ((microtime(true) - $started) >= $timeBudget) {
                    break;
                }
            }

            $checkpoint['pages_processed_last_tick'] = $processedThisTick;
            $checkpoint['progress'] = $this->calculateProgress($checkpoint);
            $checkpoint['updated_at'] = now()->toDateTimeString();
            $this->writeCheckpoint($userId, $checkpoint);

            return $this->writeRunningStatus($user, $checkpoint);
        } catch (Throwable $exception) {
            Log::error('Coach Database incremental background tick failed safely.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            $status = $this->currentStatus($userId);
            $status = array_merge($status, [
                'status' => 'failed',
                'mode' => 'full_database_reload',
                'progress' => max(1, (int) ($status['progress'] ?? 1)),
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'The incremental background reload stopped safely. The previous cached Coach Database remains available: ' . $exception->getMessage(),
            ]);
            Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));
            $this->coordinator->removePending($userId);
            $this->coordinator->releaseSharedLock($userId);
            $this->coordinator->releaseExecution($userId);

            return $status;
        } finally {
            optional($lock)->release();
        }
    }

    protected function processBusinessPage(User $user, array $checkpoint): array
    {
        $limit = max(5, min(25, (int) config('coach-database-sync.incremental.business_page_size', 10)));
        $skip = max(0, (int) ($checkpoint['business_skip'] ?? 0));
        $result = $this->coachDatabaseService->getSchoolBusinessesPageForUser($user, $skip, $limit);

        if (! ($result['success'] ?? false)) {
            $checkpoint['business_failures'] = (int) ($checkpoint['business_failures'] ?? 0) + 1;
            $checkpoint['last_school_error'] = (string) ($result['error'] ?? 'Unable to load a school page.');
            $failureLimit = max(1, (int) config('coach-database-sync.incremental.business_failure_limit', 3));

            if ($checkpoint['business_failures'] >= $failureLimit) {
                // The imported/base school directory and contact Business Name values are
                // enough to finish a useful read model. Do not fail the entire reload just
                // because the optional Businesses endpoint is temporarily slow.
                $checkpoint['business_has_more'] = false;
                $checkpoint['business_source_degraded'] = true;
                $checkpoint['warnings'][] = 'The school/company endpoint was unavailable, so existing schools and contact Business Name values were retained.';
            }

            $checkpoint['next_source'] = 'contacts';
            return $checkpoint;
        }

        foreach (($result['schools'] ?? $result['businesses'] ?? []) as $school) {
            if (! is_array($school)) {
                continue;
            }

            $key = $this->schoolIdentity($school);
            if ($key === '') {
                continue;
            }

            $checkpoint['schools'][$key] = isset($checkpoint['schools'][$key])
                ? $this->mergePreferFilled($checkpoint['schools'][$key], $school)
                : $school;
        }

        $returned = count($result['schools'] ?? $result['businesses'] ?? []);
        $nextSkip = $result['next_skip'] ?? null;
        $checkpoint['remote_total_schools'] = is_numeric($result['total'] ?? null)
            ? (int) $result['total']
            : ($checkpoint['remote_total_schools'] ?? null);
        $checkpoint['business_pages'] = (int) ($checkpoint['business_pages'] ?? 0) + 1;
        $checkpoint['business_skip'] = is_numeric($nextSkip) ? (int) $nextSkip : ($skip + max(1, $returned));
        $checkpoint['business_has_more'] = (bool) ($result['has_more'] ?? false);
        $checkpoint['next_source'] = 'contacts';

        return $checkpoint;
    }

    protected function processContactPage(User $user, array $checkpoint): array
    {
        $configuredLimit = max(10, min(50, (int) config('coach-database-sync.incremental.contact_page_size', 25)));
        $limit = max(10, min($configuredLimit, (int) ($checkpoint['contact_page_size'] ?? $configuredLimit)));
        $startAfter = $checkpoint['contacts_start_after'] ?? null;
        $startAfterId = $checkpoint['contacts_start_after_id'] ?? null;

        try {
            $result = $this->coachDatabaseService->getCoachContactsPageForUser(
                user: $user,
                startAfter: is_numeric($startAfter) ? (int) $startAfter : null,
                startAfterId: filled($startAfterId) ? (string) $startAfterId : null,
                limit: $limit,
            );
        } catch (Throwable $exception) {
            $result = [
                'success' => false,
                'contacts' => [],
                'has_more' => true,
                'temporary_failure' => true,
                'error' => $exception->getMessage(),
            ];
        }

        if (! ($result['success'] ?? false)) {
            $failures = (int) ($checkpoint['contact_failures'] ?? 0) + 1;
            $checkpoint['contact_failures'] = $failures;
            $checkpoint['last_contact_error'] = (string) ($result['error'] ?? 'Unable to load a coach page.');
            $checkpoint['contact_page_size'] = max(10, (int) floor($limit / 2));
            $checkpoint['retry_not_before'] = now()->addSeconds(min(20, 2 + ($failures * 2)))->toDateTimeString();
            $checkpoint['warnings'][] = 'A contacts page timed out and will be retried automatically with a smaller page size.';

            $failureLimit = max(2, (int) config('coach-database-sync.incremental.contact_failure_limit', 6));
            if ($failures >= $failureLimit) {
                $existing = $this->coachDatabaseService->cachedRecruitingSnapshotForUser($user) ?? [];
                if (! empty($existing['coaches'] ?? [])) {
                    $checkpoint['contacts_have_more'] = false;
                    $checkpoint['contact_source_degraded'] = true;
                    $checkpoint['warnings'][] = 'The contacts endpoint stayed unavailable, so the previous cached coach rows were preserved.';
                    $checkpoint['retry_not_before'] = null;
                } else {
                    // No old cache exists yet. Keep the checkpoint alive instead of marking
                    // the whole sync as failed; the next passive poll retries the same cursor.
                    $checkpoint['contact_failures'] = max(1, $failureLimit - 1);
                }
            }

            $checkpoint['next_source'] = (bool) ($checkpoint['business_has_more'] ?? false) ? 'businesses' : 'contacts';
            return $checkpoint;
        }

        $checkpoint['contact_failures'] = 0;
        $checkpoint['retry_not_before'] = null;
        $checkpoint['contact_page_size'] = min(
            max(10, (int) config('coach-database-sync.incremental.contact_page_size', 25)),
            max(10, $limit + 5),
        );

        foreach (($result['contacts'] ?? []) as $coach) {
            if (! is_array($coach)) {
                continue;
            }

            $key = $this->coachIdentity($coach);
            if ($key === '') {
                continue;
            }

            $checkpoint['coaches'][$key] = isset($checkpoint['coaches'][$key])
                ? $this->mergePreferFilled($checkpoint['coaches'][$key], $coach)
                : $coach;
        }

        $checkpoint['remote_total_contacts'] = is_numeric($result['total'] ?? null)
            ? (int) $result['total']
            : ($checkpoint['remote_total_contacts'] ?? null);
        $checkpoint['contact_pages'] = (int) ($checkpoint['contact_pages'] ?? 0) + 1;
        $checkpoint['contacts_start_after'] = $result['next_start_after'] ?? null;
        $checkpoint['contacts_start_after_id'] = $result['next_start_after_id'] ?? null;
        $checkpoint['contacts_have_more'] = (bool) ($result['has_more'] ?? false);
        $checkpoint['next_source'] = 'businesses';

        return $checkpoint;
    }

    protected function finalize(User $user, array $checkpoint): array
    {
        $userId = (int) $user->id;
        $cacheKey = $this->coachDatabaseService->recruitingSnapshotCacheKey($user);
        $existing = Cache::get($cacheKey, []);
        $existing = is_array($existing) ? $existing : [];
        $existingSchools = is_array($existing['schools'] ?? null) ? $existing['schools'] : [];
        $existingCoaches = is_array($existing['coaches'] ?? null) ? $existing['coaches'] : [];

        $rebuilt = $this->coachDatabaseService->rebuildFromSchoolCompanySnapshot(
            schools: array_merge($existingSchools, array_values($checkpoint['schools'] ?? [])),
            coaches: array_merge($existingCoaches, array_values($checkpoint['coaches'] ?? [])),
            user: $user,
            customListTags: is_array($existing['custom_list_tags'] ?? null) ? $existing['custom_list_tags'] : [],
        );

        $previousSchoolCount = count($existingSchools);
        $previousCoachCount = count($existingCoaches);
        $rebuiltSchoolCount = count($rebuilt['schools'] ?? []);
        $rebuiltCoachCount = count($rebuilt['coaches'] ?? []);
        $schoolFloor = $previousSchoolCount >= 20 ? (int) floor($previousSchoolCount * 0.50) : 0;
        $coachFloor = $previousCoachCount >= 20 ? (int) floor($previousCoachCount * 0.50) : 0;

        if (($schoolFloor > 0 && $rebuiltSchoolCount < $schoolFloor) || ($coachFloor > 0 && $rebuiltCoachCount < $coachFloor)) {
            throw new RuntimeException(
                "The rebuilt data looked incomplete ({$rebuiltSchoolCount} schools / {$rebuiltCoachCount} coaches). The previous cache was preserved."
            );
        }

        $finishedAt = now()->toDateTimeString();
        $warnings = array_values(array_unique(array_filter(array_map('strval', $checkpoint['warnings'] ?? []))));
        $final = array_merge($existing, $rebuilt, [
            'dataset_reconciled' => true,
            'dataset_sync_version' => (string) Str::uuid(),
            'dataset_sync_started_at' => $checkpoint['started_at'] ?? $finishedAt,
            'dataset_sync_finished_at' => $finishedAt,
            'loaded_schools_count' => $rebuiltSchoolCount,
            'loaded_contacts_count' => $rebuiltCoachCount,
            'fetched_remote_schools_count' => count($checkpoint['schools'] ?? []),
            'fetched_remote_contacts_count' => count($checkpoint['coaches'] ?? []),
            'remote_total_schools' => $checkpoint['remote_total_schools'] ?? null,
            'remote_total_contacts' => $checkpoint['remote_total_contacts'] ?? null,
            'loaded_pages' => (int) ($checkpoint['business_pages'] ?? 0) + (int) ($checkpoint['contact_pages'] ?? 0),
            'has_more_data' => false,
            'last_schools_error' => $checkpoint['last_school_error'] ?? null,
            'last_contacts_error' => $checkpoint['last_contact_error'] ?? null,
            'dataset_sync_warnings' => $warnings,
            'last_refresh_notice' => $warnings === []
                ? 'Full Coach Database reload completed.'
                : 'Coach Database reload completed with preserved fallback data for one unavailable source.',
            'cached_at' => $finishedAt,
        ]);

        if ($existing !== []) {
            Cache::put($cacheKey . ':previous', $existing, now()->addDays(7));
        }

        Cache::put($cacheKey, $final, now()->addHours((int) config('ghl.coach_database.cache_hours', 12)));

        $message = $warnings === []
            ? 'Full Coach Database reload completed. The refreshed read model is ready.'
            : 'Coach Database reload completed. Existing data was safely preserved for an unavailable source: ' . implode(' ', $warnings);

        $status = [
            'status' => 'completed',
            'mode' => 'full_database_reload',
            'launch_driver' => 'incremental_livewire',
            'resolved_driver' => 'incremental_livewire',
            'progress' => 100,
            'loaded_schools' => $rebuiltSchoolCount,
            'loaded_contacts' => $rebuiltCoachCount,
            'loaded_pages' => (int) ($checkpoint['business_pages'] ?? 0) + (int) ($checkpoint['contact_pages'] ?? 0),
            'fetched_remote_schools' => count($checkpoint['schools'] ?? []),
            'fetched_remote_contacts' => count($checkpoint['coaches'] ?? []),
            'remote_total_schools' => $checkpoint['remote_total_schools'] ?? null,
            'remote_total_contacts' => $checkpoint['remote_total_contacts'] ?? null,
            'started_at' => $checkpoint['started_at'] ?? null,
            'finished_at' => $finishedAt,
            'warnings' => $warnings,
            'message' => $message,
        ];

        Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));
        $this->deleteCheckpoint($userId);
        $this->coordinator->removePending($userId);
        $this->coordinator->releaseSharedLock($userId);
        $this->coordinator->releaseExecution($userId);

        return $status;
    }

    protected function writeRunningStatus(User $user, array $checkpoint): array
    {
        $userId = (int) $user->id;
        $loadedSchools = count($checkpoint['schools'] ?? []);
        $loadedContacts = count($checkpoint['coaches'] ?? []);
        $pages = (int) ($checkpoint['business_pages'] ?? 0) + (int) ($checkpoint['contact_pages'] ?? 0);
        $phase = (string) ($checkpoint['phase'] ?? 'fetch');
        $lastBurst = max(0, (int) ($checkpoint['pages_processed_last_tick'] ?? 0));
        $message = $phase === 'finalize'
            ? 'All available pages are loaded. Building the final school and coach indexes.'
            : "Background worker processed {$lastBurst} page(s) in the latest pass and {$pages} total ({$loadedSchools} schools, {$loadedContacts} coaches). Existing cached rows remain usable.";

        $status = array_merge($this->currentStatus($userId), [
            'status' => 'running',
            'mode' => 'full_database_reload',
            'launch_driver' => 'incremental_livewire',
            'resolved_driver' => 'incremental_livewire',
            'progress' => max(1, min(96, (int) ($checkpoint['progress'] ?? 1))),
            'loaded_schools' => $loadedSchools,
            'loaded_contacts' => $loadedContacts,
            'loaded_pages' => $pages,
            'remote_total_schools' => $checkpoint['remote_total_schools'] ?? null,
            'remote_total_contacts' => $checkpoint['remote_total_contacts'] ?? null,
            'worker_heartbeat_at' => now()->toDateTimeString(),
            'worker_host' => gethostname() ?: php_uname('n'),
            'worker_pid' => getmypid(),
            'message' => $message,
            'warnings' => array_values(array_unique($checkpoint['warnings'] ?? [])),
        ]);

        Cache::put($this->coordinator->statusKey($userId), $status, now()->addHours(6));
        Cache::put($this->coordinator->sharedLockKey($userId), $checkpoint['started_at'] ?? now()->toDateTimeString(), now()->addHours(3));

        return $status;
    }

    protected function newCheckpoint(User $user): array
    {
        return [
            'version' => 1,
            'user_id' => (int) $user->id,
            'phase' => 'fetch',
            'next_source' => 'contacts',
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'progress' => 1,
            'business_skip' => 0,
            'business_has_more' => true,
            'business_pages' => 0,
            'business_failures' => 0,
            'contacts_start_after' => null,
            'contacts_start_after_id' => null,
            'contacts_have_more' => true,
            'contact_pages' => 0,
            'contact_failures' => 0,
            'contact_page_size' => max(10, min(50, (int) config('coach-database-sync.incremental.contact_page_size', 25))),
            'retry_not_before' => null,
            'remote_total_schools' => null,
            'remote_total_contacts' => null,
            'schools' => [],
            'coaches' => [],
            'warnings' => [],
        ];
    }

    protected function nextSource(array $checkpoint): ?string
    {
        $businesses = (bool) ($checkpoint['business_has_more'] ?? false);
        $contacts = (bool) ($checkpoint['contacts_have_more'] ?? false);
        if (! $businesses && ! $contacts) {
            return null;
        }

        $preferred = (string) ($checkpoint['next_source'] ?? 'businesses');
        if ($preferred === 'businesses' && $businesses) {
            return 'businesses';
        }
        if ($preferred === 'contacts' && $contacts) {
            return 'contacts';
        }

        return $businesses ? 'businesses' : 'contacts';
    }

    protected function calculateProgress(array $checkpoint): int
    {
        if (($checkpoint['phase'] ?? '') === 'finalize') {
            return 96;
        }

        $loadedSchools = count($checkpoint['schools'] ?? []);
        $loadedContacts = count($checkpoint['coaches'] ?? []);
        $remoteSchools = $checkpoint['remote_total_schools'] ?? null;
        $remoteContacts = $checkpoint['remote_total_contacts'] ?? null;

        if (($remoteSchools ?? 0) > 0 || ($remoteContacts ?? 0) > 0) {
            $total = max(1, (int) ($remoteSchools ?? $loadedSchools) + (int) ($remoteContacts ?? $loadedContacts));
            return max(2, min(94, (int) round((min($total, $loadedSchools + $loadedContacts) / $total) * 94)));
        }

        $pages = (int) ($checkpoint['business_pages'] ?? 0) + (int) ($checkpoint['contact_pages'] ?? 0);
        return max(2, min(90, 2 + ($pages * 4)));
    }

    protected function applyBoundedHttpTimeouts(): void
    {
        config([
            'ghl.coach_database.http_connect_timeout' => max(1, (int) config('coach-database-sync.incremental.connect_timeout', 3)),
            'ghl.coach_database.http_timeout' => max(3, (int) config('coach-database-sync.incremental.request_timeout', 8)),
            'ghl.coach_database.http_retries' => 0,
            'ghl.coach_database.http_retry_sleep_ms' => 0,
        ]);
    }

    protected function schoolIdentity(array $school): string
    {
        $id = strtolower(trim((string) ($school['business_id'] ?? $school['company_id'] ?? $school['id'] ?? '')));
        if ($id !== '') {
            return 'business:' . $id;
        }

        $name = $this->normalizeName((string) ($school['name'] ?? $school['school_name'] ?? $school['company_name'] ?? ''));
        return $name !== '' ? 'school:' . $name : '';
    }

    protected function coachIdentity(array $coach): string
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

        $name = $this->normalizeName((string) ($coach['name'] ?? ''));
        $school = $this->normalizeName((string) ($coach['school'] ?? $coach['business_name'] ?? $coach['company_name'] ?? ''));
        return ($name !== '' || $school !== '') ? 'fallback:' . sha1($name . '|' . $school) : '';
    }

    protected function normalizeName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    protected function mergePreferFilled(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (! array_key_exists($key, $existing) || blank($existing[$key])) {
                $existing[$key] = $value;
            } elseif (is_array($existing[$key]) && is_array($value)) {
                $existing[$key] = array_replace_recursive($existing[$key], $value);
            }
        }

        return $existing;
    }

    protected function currentStatus(int $userId): array
    {
        $status = Cache::get($this->coordinator->statusKey($userId), []);
        return is_array($status) ? $status : [];
    }

    protected function checkpointPath(int $userId): string
    {
        return storage_path('app/recruiting-sync/dataset-' . $userId . '.json');
    }

    protected function tickLockKey(int $userId): string
    {
        return 'recruiting:dataset-sync-web-tick:' . $userId;
    }

    protected function readCheckpoint(int $userId): array
    {
        $path = $this->checkpointPath($userId);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function writeCheckpoint(int $userId, array $checkpoint): void
    {
        $path = $this->checkpointPath($userId);
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the Recruiting Center sync checkpoint directory.');
        }

        $json = json_encode($checkpoint, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode the Recruiting Center sync checkpoint.');
        }

        $temporary = $path . '.tmp-' . getmypid();
        if (@file_put_contents($temporary, $json, LOCK_EX) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write the Recruiting Center sync checkpoint.');
        }
    }

    protected function deleteCheckpoint(int $userId): void
    {
        @unlink($this->checkpointPath($userId));
    }
}