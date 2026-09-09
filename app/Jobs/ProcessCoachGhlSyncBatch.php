<?php

namespace App\Jobs;

use App\Models\Coach;
use App\Models\CoachGhlSyncRun;
use App\Models\CoachGhlSyncTarget;
use App\Models\SchoolGhlSyncTarget;
use App\Models\User;
use App\Services\CoachGhlGateway;
use App\Services\CoachGhlSyncPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCoachGhlSyncBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(
        public int $runId,
        public int $batchLimit = 100,
        public bool $dispatchNext = true,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function handle(CoachGhlGateway $gateway, CoachGhlSyncPlanner $planner): void
    {
        $run = CoachGhlSyncRun::query()->find($this->runId);
        if (! $run || in_array($run->status, ['completed', 'completed_with_errors', 'cancelled'], true)) {
            return;
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => $run->started_at ?: now(),
            'heartbeat_at' => now(),
            'message' => 'Creating or updating GHL schools, contacts, and associations…',
        ])->save();

        $targets = CoachGhlSyncTarget::query()
            ->with(['coach.school', 'representativeUser'])
            ->where('status', 'pending')
            ->orderBy('location_id')
            ->orderBy(
                Coach::query()
                    ->select('school_id')
                    ->whereColumn('coaches.id', 'coach_ghl_sync_targets.coach_id')
                    ->limit(1)
            )
            ->orderBy('id')
            ->limit(max(1, min(100, $this->batchLimit)))
            ->get();

        if ($targets->isEmpty()) {
            $this->finishRun($run);
            return;
        }

        // Re-resolve the current audience once per batch. The planner already removes
        // stale targets before a run, but this guard prevents a target from being pushed
        // if a user's sport/gender/credential changes while the run is in progress.
        $credentialGroups = $planner->credentialGroups()
            ->keyBy(fn (array $group): string => $planner->credentialGroupKey(
                (string) $group['api_key_hash'],
                (string) $group['location_id'],
            ));

        $representativeUsers = User::query()
            ->whereIn(
                'id',
                $credentialGroups->pluck('representative_user_id')->map(fn ($id): int => (int) $id)->all(),
            )
            ->get()
            ->keyBy('id');

        // Schools are synchronized inline with the first coach that needs them.
        // This avoids a separate preflight pass and lets progress move immediately.

        $consecutiveFailureMessage = null;
        $consecutiveFailureCount = 0;
        $checkpointProcessed = 0;
        $checkpointCounts = [
            'created_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($targets as $target) {
            $run->refresh();
            if (in_array($run->status, ['cancelled', 'paused'], true)) {
                break;
            }
            $target->forceFill([
                'status' => 'processing',
                'checked_at' => now(),
                'last_error' => null,
            ])->save();

            $run->forceFill([
                'current_location_id' => $target->location_id,
                'current_email' => $target->coach_email_snapshot,
                'heartbeat_at' => now(),
                'message' => 'Checking school and coach ' . $target->coach_email_snapshot . ' in GHL subaccount ' . $target->location_id,
            ])->save();

            try {
                $groupKey = $planner->credentialGroupKey(
                    (string) $target->api_key_hash,
                    (string) $target->location_id,
                );
                $group = $credentialGroups->get($groupKey);

                // Missing local records, removed credential groups, blank gender/sport, and
                // mismatched gender/sport are stale targets now. Exclude them instead of
                // attempting a remote write or repeatedly surfacing them as sync failures.
                if (! $target->coach || ! is_array($group) || ! $planner->coachMatchesGroup($target->coach, $group)) {
                    $target->forceFill([
                        'status' => 'excluded_audience',
                        'last_error' => null,
                        'checked_at' => now(),
                    ])->save();

                    $consecutiveFailureMessage = null;
                    $consecutiveFailureCount = 0;
                    continue;
                }

                $credentialUser = $representativeUsers->get((int) $group['representative_user_id']);
                if (! $credentialUser) {
                    throw new \RuntimeException(sprintf(
                        'Credential account no longer exists. User #%s · GHL location %s.',
                        (string) ($group['representative_user_id'] ?? 'unknown'),
                        (string) ($target->location_id ?: 'unknown'),
                    ));
                }

                // Keep the target's diagnostic metadata current when a shared credential
                // group changes which PLYRCARD user serves as its representative.
                if (
                    (int) $target->representative_user_id !== (int) $group['representative_user_id']
                    || $target->account_user_ids !== $group['user_ids']
                ) {
                    $target->forceFill([
                        'representative_user_id' => (int) $group['representative_user_id'],
                        'account_user_ids' => $group['user_ids'],
                    ])->save();
                }

                $result = $gateway->syncCoach(
                    $target->coach,
                    $credentialUser,
                    (string) $target->location_id,
                    (string) $target->api_key_hash,
                );

                $target->forceFill([
                    'ghl_contact_id' => $result['contact_id'] ?? null,
                    'ghl_business_id' => $result['business_id'] ?? null,
                    'status' => 'synced',
                    'matched_by' => $result['matched_by'] ?? 'email',
                    'last_error' => null,
                    'checked_at' => now(),
                    'synced_at' => now(),
                ])->save();

                $contactColumn = match ($result['action'] ?? null) {
                    'created' => 'created_count',
                    'updated' => 'updated_count',
                    default => 'unchanged_count',
                };
                $checkpointCounts[$contactColumn]++;
                $consecutiveFailureMessage = null;
                $consecutiveFailureCount = 0;

                $this->countSchoolActionOnce(
                    $run->id,
                    isset($result['school_mapping_id']) ? (int) $result['school_mapping_id'] : null,
                    (string) ($result['school_action'] ?? 'unchanged'),
                );
            } catch (Throwable $exception) {
                report($exception);

                if ($target->coach?->school_id) {
                    $failedSchoolMappingId = SchoolGhlSyncTarget::query()
                        ->where('school_id', $target->coach->school_id)
                        ->where('api_key_hash', $target->api_key_hash)
                        ->where('location_id', $target->location_id)
                        ->where('status', 'failed')
                        ->value('id');

                    $this->countSchoolActionOnce(
                        $run->id,
                        $failedSchoolMappingId ? (int) $failedSchoolMappingId : null,
                        'failed',
                    );
                }

                $target->forceFill([
                    'status' => 'failed',
                    'last_error' => $exception->getMessage(),
                    'checked_at' => now(),
                ])->save();
                $checkpointCounts['failed_count']++;

                $message = trim($exception->getMessage());
                if ($message !== '' && $message === $consecutiveFailureMessage) {
                    $consecutiveFailureCount++;
                } else {
                    $consecutiveFailureMessage = $message;
                    $consecutiveFailureCount = 1;
                }

                if ($consecutiveFailureCount >= 5) {
                    $run->forceFill([
                        'status' => 'paused',
                        'last_error' => $message,
                        'message' => 'Paused automatically after 5 identical failures. Open Backend errors to see the exact PLYRCARD credential account and GHL subaccount, fix it, then press Restart.',
                        'current_location_id' => null,
                        'current_email' => null,
                        'finished_at' => now(),
                        'heartbeat_at' => now(),
                    ])->save();

                    CoachGhlSyncTarget::query()->where('status', 'processing')->update(['status' => 'pending']);
                    SchoolGhlSyncTarget::query()->where('status', 'processing')->update(['status' => 'pending']);
                    break;
                }
            } finally {
                $checkpointProcessed++;

                // Persist progress every ten contacts. This keeps the UI feeling live
                // without doing several run-table writes for every API response.
                if ($checkpointProcessed >= 10 || $target->is($targets->last())) {
                    $this->flushCheckpoint($run->id, $checkpointProcessed, $checkpointCounts);
                    $checkpointProcessed = 0;
                    $checkpointCounts = [
                        'created_count' => 0,
                        'updated_count' => 0,
                        'unchanged_count' => 0,
                        'failed_count' => 0,
                    ];
                }
            }
        }

        $run->refresh();
        if (in_array($run->status, ['cancelled', 'paused'], true)) {
            return;
        }

        $remaining = CoachGhlSyncTarget::query()->whereIn('status', ['pending', 'processing'])->exists();
        if ($remaining && $this->dispatchNext) {
            self::dispatch($run->id)
                ->onConnection('database')
                ->onQueue('default');
        } elseif (! $remaining) {
            $this->finishRun($run->fresh());
        }
    }

    public function failed(Throwable $exception): void
    {
        CoachGhlSyncRun::query()->whereKey($this->runId)->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
            'message' => 'The background worker stopped unexpectedly.',
            'finished_at' => now(),
            'heartbeat_at' => now(),
        ]);
    }

    protected function flushCheckpoint(int $runId, int $processed, array $counts): void
    {
        DB::transaction(function () use ($runId, $processed, $counts): void {
            DB::table('coach_ghl_sync_runs')->where('id', $runId)->increment('processed', $processed);

            foreach ($counts as $column => $amount) {
                if ($amount > 0) {
                    DB::table('coach_ghl_sync_runs')->where('id', $runId)->increment($column, $amount);
                }
            }

            DB::table('coach_ghl_sync_runs')->where('id', $runId)->update([
                'heartbeat_at' => now(),
            ]);
        });
    }

    protected function countSchoolActionOnce(int $runId, ?int $mappingId, string $action): void
    {
        if (! $mappingId) {
            return;
        }

        $claimed = SchoolGhlSyncTarget::query()
            ->whereKey($mappingId)
            ->where(function ($query) use ($runId): void {
                $query->whereNull('last_counted_run_id')
                    ->orWhere('last_counted_run_id', '!=', $runId);
            })
            ->update(['last_counted_run_id' => $runId]);

        if ($claimed !== 1) {
            return;
        }

        $column = match ($action) {
            'created' => 'school_created_count',
            'updated' => 'school_updated_count',
            'failed' => 'school_failed_count',
            default => 'school_unchanged_count',
        };

        DB::table('coach_ghl_sync_runs')->where('id', $runId)->increment($column);
    }

    protected function finishRun(CoachGhlSyncRun $run): void
    {
        $run->refresh();
        $hasErrors = $run->failed_count > 0 || $run->school_failed_count > 0;

        $run->forceFill([
            'status' => $hasErrors ? 'completed_with_errors' : 'completed',
            'processed' => $run->total,
            'current_location_id' => null,
            'current_email' => null,
            'message' => $hasErrors
                ? 'Synchronization completed with some failed schools or contacts.'
                : 'All pending schools and contacts have been synchronized.',
            'finished_at' => now(),
            'heartbeat_at' => now(),
        ])->save();
    }
}