<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CoachDatabaseDatasetSyncService
{
    public function __construct(
        protected CoachDatabaseService $coachDatabaseService,
    ) {}

    /**
     * Build the complete Recruiting Center read model outside an HTTP/Livewire request.
     *
     * The current snapshot is never cleared while this runs. Schools and contacts are
     * accumulated in CLI memory, reconciled once, and atomically swapped into cache only
     * after the complete build succeeds.
     */
    public function sync(User $user, bool $force = false): array
    {
        $this->prepareCliRuntime();

        $cacheKey = $this->coachDatabaseService->recruitingSnapshotCacheKey($user);
        $statusKey = $this->statusKey($user);
        $lockKey = $this->lockKey($user);
        $existing = Cache::get($cacheKey, []);
        $existing = is_array($existing) ? $existing : [];

        $businessLimit = max(5, min(50, (int) config('coach-database-sync.pages.businesses', 25)));
        $contactLimit = max(10, min(100, (int) config('coach-database-sync.pages.contacts', 50)));
        $maxBusinessPages = max(1, (int) config('coach-database-sync.max_pages.businesses', 500));
        $maxContactPages = max(1, (int) config('coach-database-sync.max_pages.contacts', 500));

        $schoolsByKey = [];
        $coachesByKey = [];
        $businessSkip = 0;
        $contactsStartAfter = null;
        $contactsStartAfterId = null;
        $businessHasMore = true;
        $contactsHaveMore = true;
        $businessPages = 0;
        $contactPages = 0;
        $remoteSchools = null;
        $remoteContacts = null;
        $startedAt = now()->toDateTimeString();
        $lastSchoolError = null;
        $lastContactError = null;
        $businessFailures = 0;
        $contactFailures = 0;
        $warnings = [];

        $this->writeStatus($user, [
            'status' => 'running',
            'mode' => 'full_database_reload',
            'progress' => 1,
            'loaded_schools' => 0,
            'loaded_contacts' => 0,
            'loaded_pages' => 0,
            'started_at' => $startedAt,
            'worker_started_at' => $startedAt,
            'worker_heartbeat_at' => $startedAt,
            'message' => 'Loading school and coach records in background pages. Existing cached data remains visible.',
        ]);

        try {
            while ($businessHasMore || $contactsHaveMore) {
                if ($businessHasMore) {
                    if ($businessPages >= $maxBusinessPages) {
                        throw new RuntimeException("School pagination exceeded {$maxBusinessPages} pages. The previous cache was kept.");
                    }

                    $previousSkip = $businessSkip;
                    $result = $this->attemptPage(
                        fn (): array => $this->coachDatabaseService->getSchoolBusinessesPageForUser(
                            user: $user,
                            skip: $businessSkip,
                            limit: $businessLimit,
                        ),
                        'schools',
                    );

                    if (! ($result['success'] ?? false)) {
                        $lastSchoolError = (string) ($result['error'] ?? 'Unable to load a school page.');
                        $businessFailures++;
                        $failureLimit = max(1, (int) config('coach-database-sync.business_failure_limit', 2));

                        if ($businessFailures >= $failureLimit) {
                            // Business/company records are an enrichment source, not the only
                            // source of schools. Preserve the imported school directory and use
                            // each contact's Business/Company/School Name instead of failing the
                            // complete reload when this endpoint is temporarily slow.
                            $businessHasMore = false;
                            $warnings[] = 'The school/company endpoint was unavailable, so existing schools and contact Business Name values were preserved.';
                        }
                    } else {

                    foreach (($result['schools'] ?? $result['businesses'] ?? []) as $school) {
                        if (! is_array($school)) {
                            continue;
                        }

                        $key = $this->schoolIdentity($school);
                        if ($key === '') {
                            continue;
                        }

                        $schoolsByKey[$key] = isset($schoolsByKey[$key])
                            ? $this->mergePreferFilled($schoolsByKey[$key], $school)
                            : $school;
                    }

                    $remoteSchools = is_numeric($result['total'] ?? null)
                        ? (int) $result['total']
                        : $remoteSchools;
                    $businessSkip = is_numeric($result['next_skip'] ?? null)
                        ? (int) $result['next_skip']
                        : ($businessSkip + $businessLimit);
                    $businessHasMore = (bool) ($result['has_more'] ?? false);
                    $businessPages++;

                    if ($businessHasMore && $businessSkip <= $previousSkip) {
                        throw new RuntimeException('School pagination did not advance. The previous cache was kept.');
                    }
                    }
                }

                if ($contactsHaveMore) {
                    if ($contactPages >= $maxContactPages) {
                        throw new RuntimeException("Contact pagination exceeded {$maxContactPages} pages. The previous cache was kept.");
                    }

                    $previousAfter = $contactsStartAfter;
                    $previousAfterId = $contactsStartAfterId;
                    $result = $this->attemptPage(
                        fn (): array => $this->coachDatabaseService->getCoachContactsPageForUser(
                            user: $user,
                            startAfter: is_numeric($contactsStartAfter) ? (int) $contactsStartAfter : null,
                            startAfterId: filled($contactsStartAfterId) ? (string) $contactsStartAfterId : null,
                            limit: $contactLimit,
                        ),
                        'contacts',
                    );

                    if (! ($result['success'] ?? false)) {
                        $lastContactError = (string) ($result['error'] ?? 'Unable to load a coach page.');
                        $contactFailures++;
                        $failureLimit = max(1, (int) config('coach-database-sync.contact_failure_limit', 3));

                        if ($contactFailures >= $failureLimit) {
                            $existingCoachesForFallback = is_array($existing['coaches'] ?? null) ? $existing['coaches'] : [];
                            if ($existingCoachesForFallback !== []) {
                                $contactsHaveMore = false;
                                $warnings[] = 'The contacts endpoint was temporarily unavailable, so the previous cached coach rows were preserved.';
                            } else {
                                throw new RuntimeException($lastContactError);
                            }
                        }
                    } else {

                    foreach (($result['contacts'] ?? []) as $coach) {
                        if (! is_array($coach)) {
                            continue;
                        }

                        $key = $this->coachIdentity($coach);
                        if ($key === '') {
                            continue;
                        }

                        $coachesByKey[$key] = isset($coachesByKey[$key])
                            ? $this->mergePreferFilled($coachesByKey[$key], $coach)
                            : $coach;
                    }

                    $remoteContacts = is_numeric($result['total'] ?? null)
                        ? (int) $result['total']
                        : $remoteContacts;
                    $contactsStartAfter = $result['next_start_after'] ?? null;
                    $contactsStartAfterId = $result['next_start_after_id'] ?? null;
                    $contactsHaveMore = (bool) ($result['has_more'] ?? false);
                    $contactPages++;

                    if (
                        $contactsHaveMore
                        && (string) $contactsStartAfter === (string) $previousAfter
                        && (string) $contactsStartAfterId === (string) $previousAfterId
                    ) {
                        throw new RuntimeException('Coach pagination did not advance. The previous cache was kept.');
                    }
                    }
                }

                $loadedSchools = count($schoolsByKey);
                $loadedContacts = count($coachesByKey);
                $progress = $this->calculateProgress(
                    loadedSchools: $loadedSchools,
                    loadedContacts: $loadedContacts,
                    remoteSchools: $remoteSchools,
                    remoteContacts: $remoteContacts,
                    businessDone: ! $businessHasMore,
                    contactsDone: ! $contactsHaveMore,
                    pages: $businessPages + $contactPages,
                );

                // Refresh the lock TTL and expose only lightweight progress to Livewire.
                Cache::put($lockKey, $startedAt, now()->addMinutes(90));
                $this->writeStatus($user, [
                    'status' => 'running',
                    'mode' => 'full_database_reload',
                    'progress' => $progress,
                    'loaded_schools' => $loadedSchools,
                    'loaded_contacts' => $loadedContacts,
                    'loaded_pages' => $businessPages + $contactPages,
                    'remote_total_schools' => $remoteSchools,
                    'remote_total_contacts' => $remoteContacts,
                    'started_at' => $startedAt,
                    'message' => "Loaded {$loadedSchools} schools and {$loadedContacts} coaches. Reconciling school associations and Business Name fields in background pages.",
                ]);

                if ((($businessPages + $contactPages) % 5) === 0) {
                    gc_collect_cycles();
                }
            }

            $this->writeStatus($user, [
                'status' => 'running',
                'mode' => 'full_database_reload',
                'progress' => 96,
                'loaded_schools' => count($schoolsByKey),
                'loaded_contacts' => count($coachesByKey),
                'loaded_pages' => $businessPages + $contactPages,
                'fetched_remote_schools' => count($schoolsByKey),
                'fetched_remote_contacts' => count($coachesByKey),
                'remote_total_schools' => $remoteSchools,
                'remote_total_contacts' => $remoteContacts,
                'started_at' => $startedAt,
                'message' => 'All school and coach pages are loaded. Building the final cross-referenced indexes.',
            ]);

            // The cached Coach Database may contain the imported/base school directory
            // in addition to records currently represented in the connected CRM. A CRM
            // refresh must enrich that directory, never replace it with only the two or
            // three Business records returned by the location API.
            $existingSchools = is_array($existing['schools'] ?? null) ? $existing['schools'] : [];
            $existingCoaches = is_array($existing['coaches'] ?? null) ? $existing['coaches'] : [];

            $rebuilt = $this->coachDatabaseService->rebuildFromSchoolCompanySnapshot(
                schools: array_merge($existingSchools, array_values($schoolsByKey)),
                coaches: array_merge($existingCoaches, array_values($coachesByKey)),
                user: $user,
                customListTags: is_array($existing['custom_list_tags'] ?? null) ? $existing['custom_list_tags'] : [],
            );

            $previousSchoolCount = count($existingSchools);
            $previousCoachCount = count($existingCoaches);
            $rebuiltSchoolCount = count($rebuilt['schools'] ?? []);
            $rebuiltCoachCount = count($rebuilt['coaches'] ?? []);

            // Never atomically replace a healthy production snapshot with a clearly
            // incomplete result. This protects against partial API payloads, missing
            // custom-field mappings, and accidental source changes.
            $schoolFloor = $previousSchoolCount >= 20 ? (int) floor($previousSchoolCount * 0.50) : 0;
            $coachFloor = $previousCoachCount >= 20 ? (int) floor($previousCoachCount * 0.50) : 0;

            if (
                ($schoolFloor > 0 && $rebuiltSchoolCount < $schoolFloor)
                || ($coachFloor > 0 && $rebuiltCoachCount < $coachFloor)
            ) {
                throw new RuntimeException(
                    'The refreshed dataset was unexpectedly incomplete '
                    . "({$rebuiltSchoolCount} schools / {$rebuiltCoachCount} coaches; previous "
                    . "{$previousSchoolCount} / {$previousCoachCount}). The previous cache was preserved."
                );
            }

            $finishedAt = now()->toDateTimeString();
            $final = array_merge($existing, $rebuilt, [
                'dataset_reconciled' => true,
                'dataset_sync_version' => (string) Str::uuid(),
                'dataset_sync_started_at' => $startedAt,
                'dataset_sync_finished_at' => $finishedAt,
                'loaded_schools_count' => count($rebuilt['schools'] ?? []),
                'loaded_contacts_count' => count($rebuilt['coaches'] ?? []),
                'fetched_remote_schools_count' => count($schoolsByKey),
                'fetched_remote_contacts_count' => count($coachesByKey),
                'remote_total_schools' => $remoteSchools,
                'remote_total_contacts' => $remoteContacts,
                'loaded_pages' => $businessPages + $contactPages,
                'next_business_skip' => $businessSkip,
                'businesses_have_more' => false,
                'next_contacts_start_after' => $contactsStartAfter,
                'next_contacts_start_after_id' => $contactsStartAfterId,
                'contacts_have_more' => false,
                'has_more_data' => false,
                'last_schools_error' => $lastSchoolError,
                'last_contacts_error' => $lastContactError,
                'dataset_sync_warnings' => array_values(array_unique($warnings)),
                'last_refresh_notice' => 'Full Coach Database background reload completed. Schools and coaches were cross-referenced by association ID and Business/Company/School Name.',
                'cached_at' => $finishedAt,
            ]);

            // Keep one rollback snapshot before the atomic swap. This does not
            // affect the active cache and gives production a recovery point if a future
            // API/schema change produces an unexpected read model.
            if ($existing !== []) {
                Cache::put($cacheKey . ':previous', $existing, now()->addDays(7));
            }

            Cache::put(
                $cacheKey,
                $final,
                now()->addHours((int) config('ghl.coach_database.cache_hours', 12)),
            );

            $status = [
                'status' => 'completed',
                'mode' => 'full_database_reload',
                'progress' => 100,
                'loaded_schools' => (int) $final['loaded_schools_count'],
                'loaded_contacts' => (int) $final['loaded_contacts_count'],
                'loaded_pages' => $businessPages + $contactPages,
                'fetched_remote_schools' => count($schoolsByKey),
                'fetched_remote_contacts' => count($coachesByKey),
                'remote_total_schools' => $remoteSchools,
                'remote_total_contacts' => $remoteContacts,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'warnings' => array_values(array_unique($warnings)),
                'message' => $warnings === []
                    ? 'Full Coach Database reload completed. The base directory was preserved and refreshed contact/business data was merged into it.'
                    : 'Coach Database reload completed with safe fallback data for an unavailable source: ' . implode(' ', array_values(array_unique($warnings))),
            ];
            $this->writeStatus($user, $status);

            return array_merge(['success' => true], $status);
        } catch (Throwable $exception) {
            Log::error('Coach Database background dataset sync failed safely.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'loaded_schools' => count($schoolsByKey),
                'loaded_contacts' => count($coachesByKey),
                'business_pages' => $businessPages,
                'contact_pages' => $contactPages,
            ]);

            $status = [
                'status' => 'failed',
                'mode' => 'full_database_reload',
                'progress' => 0,
                'loaded_schools' => count($schoolsByKey),
                'loaded_contacts' => count($coachesByKey),
                'loaded_pages' => $businessPages + $contactPages,
                'started_at' => $startedAt,
                'failed_at' => now()->toDateTimeString(),
                'error' => $exception->getMessage(),
                'message' => 'Background reload failed, but the previous cached Coach Database was preserved: ' . $exception->getMessage(),
            ];
            $this->writeStatus($user, $status);

            return array_merge(['success' => false], $status);
        }
    }

    protected function attemptPage(callable $callback, string $label): array
    {
        $attempts = max(1, (int) config('coach-database-sync.page_attempts', 3));
        $sleepMs = max(0, (int) config('coach-database-sync.retry_sleep_ms', 800));
        $last = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $last = $callback();
                if (($last['success'] ?? false) === true) {
                    return $last;
                }
            } catch (Throwable $exception) {
                $last = ['success' => false, 'error' => $exception->getMessage()];
            }

            if ($attempt < $attempts && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        Log::warning("Coach Database {$label} page exhausted retries.", [
            'attempts' => $attempts,
            'error' => $last['error'] ?? null,
        ]);

        return $last ?: ['success' => false, 'error' => "Unable to load {$label} page."];
    }

    protected function calculateProgress(
        int $loadedSchools,
        int $loadedContacts,
        ?int $remoteSchools,
        ?int $remoteContacts,
        bool $businessDone,
        bool $contactsDone,
        int $pages,
    ): int {
        if (($remoteSchools ?? 0) > 0 || ($remoteContacts ?? 0) > 0) {
            $total = max(1, (int) ($remoteSchools ?? $loadedSchools) + (int) ($remoteContacts ?? $loadedContacts));
            $loaded = min($total, $loadedSchools + $loadedContacts);
            return max(2, min(94, (int) round(($loaded / $total) * 94)));
        }

        if ($businessDone && $contactsDone) {
            return 94;
        }

        return max(2, min(90, 2 + ($pages * 2)));
    }

    protected function schoolIdentity(array $school): string
    {
        $businessId = strtolower(trim((string) ($school['business_id'] ?? $school['id'] ?? '')));
        if ($businessId !== '') {
            return 'business:' . $businessId;
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
                continue;
            }

            if (is_array($existing[$key]) && is_array($value)) {
                $existing[$key] = array_replace_recursive($existing[$key], $value);
            }
        }

        return $existing;
    }

    protected function prepareCliRuntime(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Full Coach Database dataset sync must run from CLI/background processing.');
        }

        @set_time_limit(0);
        $memory = trim((string) config('coach-database-sync.cli_memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        config([
            'ghl.coach_database.http_connect_timeout' => max(1, (int) config('coach-database-sync.http.connect_timeout', 5)),
            'ghl.coach_database.http_timeout' => max(5, (int) config('coach-database-sync.http.request_timeout', 20)),
        ]);
    }

    protected function writeStatus(User $user, array $status): void
    {
        $existing = Cache::get($this->statusKey($user), []);
        $existing = is_array($existing) ? $existing : [];

        $status = array_merge($existing, $status, [
            'user_id' => $user->id,
            'worker_heartbeat_at' => now()->toDateTimeString(),
            'worker_host' => gethostname() ?: php_uname('n'),
            'worker_pid' => getmypid(),
        ]);

        Cache::put($this->statusKey($user), $status, now()->addHours(6));
        Cache::put($this->lockKey($user), $status['started_at'] ?? now()->toDateTimeString(), now()->addHours(3));
    }

    protected function statusKey(User $user): string
    {
        return 'recruiting:stats-sync-status:' . $user->id;
    }

    protected function lockKey(User $user): string
    {
        return 'recruiting:stats-sync-running:' . $user->id;
    }
}
