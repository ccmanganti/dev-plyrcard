<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CoachDatabaseActionQueueService
{
    public static function queueKey(User $user): string
    {
        return 'recruiting:action-queue:' . $user->id;
    }

    public static function queueLockKey(User $user): string
    {
        return 'recruiting:action-queue-lock:' . $user->id;
    }

    public static function workerLockKey(User $user): string
    {
        return 'recruiting:action-worker:' . $user->id;
    }

    public static function statusKey(User $user): string
    {
        return 'recruiting:action-status:' . $user->id;
    }

    public static function launchKey(User $user): string
    {
        return 'recruiting:action-launch:' . $user->id;
    }

    public function enqueue(User $user, array $actions): array
    {
        $normalized = collect($actions)
            ->filter(fn ($action): bool => is_array($action))
            ->map(function (array $action): ?array {
                $kind = strtolower(trim((string) ($action['kind'] ?? 'contact_tag')));
                $kind = $kind === 'school_list_bulk' ? 'school_list_bulk' : 'contact_tag';
                $tag = trim((string) ($action['tag'] ?? ''));
                $type = strtolower(trim((string) ($action['type'] ?? 'add'))) === 'remove'
                    ? 'remove'
                    : 'add';

                $contactIds = collect($action['contact_ids'] ?? [])
                    ->map(fn ($id): string => trim((string) $id))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $schoolIds = collect($action['school_ids'] ?? [$action['school_id'] ?? null])
                    ->map(fn ($id): string => trim((string) $id))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($tag === '') {
                    return null;
                }

                if ($kind === 'school_list_bulk' && empty($schoolIds)) {
                    return null;
                }

                if ($kind === 'contact_tag' && empty($contactIds)) {
                    return null;
                }

                $identity = $kind === 'school_list_bulk'
                    ? implode(',', $schoolIds)
                    : implode(',', $contactIds);

                $coalesceKey = sha1($kind . '|' . $identity . '|' . strtolower($tag));

                return [
                    'id' => (string) ($action['id'] ?? Str::uuid()),
                    'kind' => $kind,
                    'coalesce_key' => $coalesceKey,
                    'school_id' => trim((string) ($action['school_id'] ?? ($schoolIds[0] ?? ''))),
                    'school_ids' => $schoolIds,
                    'list_key' => trim((string) ($action['list_key'] ?? '')),
                    'contact_ids' => $contactIds,
                    'contact_batch_size' => max(1, min(
                        50,
                        (int) ($action['contact_batch_size']
                            ?? config('ghl.coach_database.bulk_list_contacts_per_second', 50))
                    )),
                    'tag' => $tag,
                    'type' => $type,
                    'queued_at' => now()->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (empty($normalized)) {
            return [
                'success' => false,
                'queued' => 0,
                'error' => 'No valid actions were provided.',
            ];
        }

        $lock = Cache::lock(self::queueLockKey($user), 10);
        $acquired = false;

        try {
            $lock->block(2);
            $acquired = true;

            $queue = collect(Cache::get(self::queueKey($user), []))
                ->filter(fn ($action): bool => is_array($action))
                ->keyBy(
                    fn (array $action): string => (string) (
                        $action['coalesce_key'] ?? $action['id'] ?? Str::uuid()
                    )
                );

            foreach ($normalized as $action) {
                $queue->put($action['coalesce_key'], $action);
            }

            Cache::put(self::queueKey($user), $queue->values()->all(), now()->addDay());
            Cache::put(self::statusKey($user), [
                'status' => 'queued',
                'queued' => $queue->count(),
                'updated_at' => now()->toIso8601String(),
            ], now()->addDay());
        } catch (\Throwable $exception) {
            Log::warning('Recruiting action queue was briefly busy.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'queued' => 0,
                'error' => 'The action queue is busy. Please click the list again.',
            ];
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }

        return ['success' => true, 'queued' => count($normalized)];
    }

    public function process(User $user): array
    {
        $workerLock = Cache::lock(self::workerLockKey($user), 3600);

        if (! $workerLock->get()) {
            Cache::forget(self::launchKey($user));
            return ['success' => true, 'status' => 'already_running'];
        }

        $processed = 0;
        $failed = 0;
        $processedContacts = 0;
        $failedContacts = 0;

        try {
            Cache::put(self::statusKey($user), [
                'status' => 'running',
                'started_at' => now()->toIso8601String(),
                'processed' => 0,
                'failed' => 0,
                'processed_contacts' => 0,
                'failed_contacts' => 0,
            ], now()->addDay());

            $idlePasses = 0;

            for ($pass = 0; $pass < 40; $pass++) {
                $actions = $this->takeQueuedActions($user);

                if (empty($actions)) {
                    $idlePasses++;

                    if ($idlePasses >= 3) {
                        break;
                    }

                    usleep(150000);
                    continue;
                }

                $idlePasses = 0;

                foreach ($actions as $action) {
                    try {
                        $contactIds = collect($action['contact_ids'] ?? [])
                            ->map(fn ($id): string => trim((string) $id))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        if (($action['kind'] ?? 'contact_tag') === 'school_list_bulk') {
                            $contactIds = $this->resolveContactIdsForSchools(
                                $user,
                                is_array($action['school_ids'] ?? null)
                                    ? $action['school_ids']
                                    : [],
                            );
                        }

                        if (empty($contactIds)) {
                            $failed++;

                            Log::warning('Recruiting queued action had no matching coach contacts.', [
                                'user_id' => $user->id,
                                'action_id' => $action['id'] ?? null,
                                'school_count' => count($action['school_ids'] ?? []),
                            ]);

                            continue;
                        }

                        $batchSize = max(1, min(
                            50,
                            (int) ($action['contact_batch_size']
                                ?? config('ghl.coach_database.bulk_list_contacts_per_second', 50))
                        ));

                        $chunks = array_chunk($contactIds, $batchSize);
                        $actionHadSuccess = false;
                        $actionHadFailure = false;

                        foreach ($chunks as $chunkIndex => $chunk) {
                            $result = $this->updateContactChunkWithRetry(
                                user: $user,
                                contactIds: $chunk,
                                tag: (string) ($action['tag'] ?? ''),
                                type: (string) ($action['type'] ?? 'add'),
                            );

                            $updatedCount = count($result['updated_contact_ids'] ?? []);
                            if ($updatedCount === 0 && ($result['success'] ?? false)) {
                                $updatedCount = count($chunk);
                            }

                            $failedCount = count($result['failed_contact_ids'] ?? []);
                            if (! ($result['success'] ?? false)
                                && ! ($result['partial_success'] ?? false)
                                && $failedCount === 0) {
                                $failedCount = count($chunk);
                            }

                            $processedContacts += $updatedCount;
                            $failedContacts += $failedCount;

                            if (($result['success'] ?? false) || ($result['partial_success'] ?? false)) {
                                $actionHadSuccess = true;
                            }

                            if (! ($result['success'] ?? false) || $failedCount > 0) {
                                $actionHadFailure = true;
                            }

                            Cache::put(self::statusKey($user), [
                                'status' => 'running',
                                'processed' => $processed,
                                'failed' => $failed,
                                'processed_contacts' => $processedContacts,
                                'failed_contacts' => $failedContacts,
                                'current_action_id' => $action['id'] ?? null,
                                'current_chunk' => $chunkIndex + 1,
                                'total_chunks' => count($chunks),
                                'current_batch_size' => count($chunk),
                                'updated_at' => now()->toIso8601String(),
                            ], now()->addDay());

                            // User requested a maximum throughput of 50 contacts
                            // each second. One bulk request is made per chunk,
                            // then the worker waits until the next second.
                            if ($chunkIndex < count($chunks) - 1) {
                                sleep(1);
                            }
                        }

                        if ($actionHadSuccess) {
                            $processed++;
                        }

                        if ($actionHadFailure || ! $actionHadSuccess) {
                            $failed++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;

                        Log::warning('Recruiting queued contact action threw an exception.', [
                            'user_id' => $user->id,
                            'action_id' => $action['id'] ?? null,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                usleep(50000);
            }

            $remaining = count(Cache::get(self::queueKey($user), []));
            $status = ($failed > 0 || $failedContacts > 0)
                ? 'completed_with_errors'
                : 'completed';

            Cache::put(self::statusKey($user), [
                'status' => $status,
                'processed' => $processed,
                'failed' => $failed,
                'processed_contacts' => $processedContacts,
                'failed_contacts' => $failedContacts,
                'remaining' => $remaining,
                'completed_at' => now()->toIso8601String(),
            ], now()->addDay());

            return [
                'success' => $failed === 0 && $failedContacts === 0,
                'status' => $status,
                'processed' => $processed,
                'failed' => $failed,
                'processed_contacts' => $processedContacts,
                'failed_contacts' => $failedContacts,
                'remaining' => $remaining,
            ];
        } finally {
            Cache::forget(self::launchKey($user));
            optional($workerLock)->release();
        }
    }

    protected function updateContactChunkWithRetry(
        User $user,
        array $contactIds,
        string $tag,
        string $type,
    ): array {
        $maximumAttempts = max(1, (int) config(
            'ghl.coach_database.bulk_list_retry_attempts',
            4
        ));

        $lastResult = [
            'success' => false,
            'updated_contact_ids' => [],
            'failed_contact_ids' => $contactIds,
            'error' => 'Unable to update this contact batch.',
        ];

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            $lastResult = app(CoachDatabaseService::class)->updateContactsWithTag(
                $user,
                $contactIds,
                $tag,
                $type,
            );

            if (($lastResult['success'] ?? false)
                || ($lastResult['partial_success'] ?? false)) {
                return $lastResult;
            }

            $status = (int) ($lastResult['status'] ?? 0);
            $retryable = $status === 429 || $status === 408 || $status >= 500;

            if (! $retryable || $attempt >= $maximumAttempts) {
                break;
            }

            $retryAfter = max(
                1,
                (int) ($lastResult['retry_after']
                    ?? min(8, 2 ** ($attempt - 1)))
            );

            Log::warning('Retrying Recruiting Center contact-tag batch.', [
                'user_id' => $user->id,
                'attempt' => $attempt,
                'next_delay_seconds' => $retryAfter,
                'status' => $status,
                'contact_count' => count($contactIds),
            ]);

            sleep($retryAfter);
        }

        return $lastResult;
    }

    protected function resolveContactIdsForSchools(User $user, array $schoolIds): array
    {
        $schoolLookup = array_fill_keys(
            collect($schoolIds)
                ->map(fn ($id): string => trim((string) $id))
                ->filter()
                ->unique()
                ->all(),
            true,
        );

        if (empty($schoolLookup)) {
            return [];
        }

        $snapshot = app(CoachDatabaseService::class)
            ->cachedRecruitingSnapshotForUser($user) ?? [];

        $selectedBusinessIds = [];
        $selectedNames = [];

        foreach (collect($snapshot['schools'] ?? [])
            ->filter(fn ($school): bool => is_array($school)) as $school) {
            $name = strtolower(trim((string) ($school['name'] ?? '')));
            $id = trim((string) ($school['id'] ?? ''));
            $businessId = trim((string) (
                $school['business_id'] ?? $school['company_id'] ?? ''
            ));

            $candidates = array_filter([
                $id,
                $businessId,
                $name !== '' ? md5($name) : '',
            ]);

            $selected = collect($candidates)->contains(
                fn (string $candidate): bool => isset($schoolLookup[$candidate])
            );

            if (! $selected) {
                continue;
            }

            if ($businessId !== '') {
                $selectedBusinessIds[strtolower($businessId)] = true;
            }

            if ($id !== '') {
                $selectedBusinessIds[strtolower($id)] = true;
            }

            if ($name !== '') {
                $selectedNames[$this->normalizeSchoolName($name)] = true;
            }
        }

        return collect($snapshot['coaches'] ?? $snapshot['contacts'] ?? [])
            ->filter(fn ($coach): bool => is_array($coach))
            ->filter(function (array $coach) use (
                $selectedBusinessIds,
                $selectedNames
            ): bool {
                $businessIds = collect([
                    $coach['business_id'] ?? null,
                    $coach['company_id'] ?? null,
                    $coach['ghl_business_id'] ?? null,
                    data_get($coach, 'business.id'),
                    data_get($coach, 'company.id'),
                ])
                    ->map(fn ($id): string => strtolower(trim((string) $id)))
                    ->filter();

                if ($businessIds->contains(
                    fn (string $id): bool => isset($selectedBusinessIds[$id])
                )) {
                    return true;
                }

                $names = collect([
                    $coach['school'] ?? null,
                    $coach['school_name'] ?? null,
                    $coach['company_name'] ?? null,
                    $coach['business_name'] ?? null,
                    $coach['companyName'] ?? null,
                    $coach['businessName'] ?? null,
                ])
                    ->map(fn ($name): string => $this->normalizeSchoolName(
                        (string) $name
                    ))
                    ->filter();

                return $names->contains(
                    fn (string $name): bool => isset($selectedNames[$name])
                );
            })
            ->map(fn (array $coach): string => trim((string) (
                $coach['id'] ?? $coach['contact_id'] ?? ''
            )))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeSchoolName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function takeQueuedActions(User $user): array
    {
        $lock = Cache::lock(self::queueLockKey($user), 10);
        $acquired = false;

        try {
            $lock->block(2);
            $acquired = true;

            $actions = Cache::get(self::queueKey($user), []);
            Cache::put(self::queueKey($user), [], now()->addDay());

            return is_array($actions) ? array_values($actions) : [];
        } catch (\Throwable $exception) {
            Log::debug('Recruiting action worker will retry the busy queue.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }
}