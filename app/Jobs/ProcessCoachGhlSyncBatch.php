<?php

namespace App\Jobs;

use App\Models\Coach;
use App\Models\CoachGhlSyncRun;
use App\Models\CoachGhlSyncTarget;
use App\Models\SchoolGhlSyncTarget;
use App\Services\CoachGhlGateway;
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

    public int $timeout = 240;
    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(public int $runId)
    {
        $this->onConnection('database');
        $this->onQueue('coach-ghl-sync');
    }

    public function handle(CoachGhlGateway $gateway): void
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
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('location_id')
            ->orderBy(
                Coach::query()
                    ->select('school_id')
                    ->whereColumn('coaches.id', 'coach_ghl_sync_targets.coach_id')
                    ->limit(1)
            )
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($targets->isEmpty()) {
            $this->finishRun($run);
            return;
        }

        // Prime each unique school/location mapping once before processing its coaches.
        // Subsequent coaches for the same school use the saved mapping and avoid another
        // remote business-directory scan.
        $targets
            ->filter(fn ($target): bool => (bool) $target->coach?->school && (bool) $target->representativeUser)
            ->unique(fn ($target): string => implode('|', [
                $target->location_id,
                $target->api_key_hash,
                $target->coach->school_id,
            ]))
            ->each(function ($target) use ($gateway): void {
                try {
                    $gateway->syncSchool(
                        $target->coach->school,
                        $target->representativeUser,
                        (string) $target->location_id,
                        (string) $target->api_key_hash,
                    );
                } catch (Throwable $exception) {
                    // The coach iteration below records the affected coach failure and
                    // exposes the school error without stopping the rest of the batch.
                    report($exception);
                }
            });

        $consecutiveFailureMessage = null;
        $consecutiveFailureCount = 0;

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
                if (! $target->coach || ! $target->representativeUser) {
                    throw new \RuntimeException('The local coach or credential account no longer exists.');
                }

                $result = $gateway->syncCoach(
                    $target->coach,
                    $target->representativeUser,
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
                DB::table('coach_ghl_sync_runs')->where('id', $run->id)->increment($contactColumn);
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
                DB::table('coach_ghl_sync_runs')->where('id', $run->id)->increment('failed_count');

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
                        'message' => 'Paused automatically after 5 identical failures. Fix the GHL credential, permission, API version, or payload error, then press Restart.',
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
                DB::table('coach_ghl_sync_runs')->where('id', $run->id)->increment('processed');
                DB::table('coach_ghl_sync_runs')->where('id', $run->id)->update(['heartbeat_at' => now()]);
            }
        }

        $run->refresh();
        if (in_array($run->status, ['cancelled', 'paused'], true)) {
            return;
        }

        $remaining = CoachGhlSyncTarget::query()->whereIn('status', ['pending', 'processing'])->exists();
        if ($remaining) {
            self::dispatch($run->id);
        } else {
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