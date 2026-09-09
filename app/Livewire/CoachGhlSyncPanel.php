<?php

namespace App\Livewire;

use App\Jobs\ProcessCoachGhlSyncBatch;
use App\Models\Coach;
use App\Models\CoachGhlSyncRun;
use App\Models\CoachGhlSyncTarget;
use App\Models\SchoolGhlSyncTarget;
use App\Models\User;
use App\Services\CoachGhlBackgroundLauncher;
use App\Services\CoachGhlSyncPlanner;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Throwable;

class CoachGhlSyncPanel extends Component
{
    public ?int $activeRunId = null;

    public function mount(): void
    {
        $this->activeRunId = CoachGhlSyncRun::query()->latest('id')->value('id');
    }

    public function pollStatus(): void
    {
        if ($this->activeRunId && ! CoachGhlSyncRun::query()->whereKey($this->activeRunId)->exists()) {
            $this->activeRunId = CoachGhlSyncRun::query()->latest('id')->value('id');
        }

        $run = $this->run;
        if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
            return;
        }

        $lock = Cache::lock('coach-ghl-web-tick:' . $run->id, 120);
        if (! $lock->get()) {
            return;
        }

        try {
            // Process a deliberately small slice inside this Livewire request.
            // This is the command-free production fallback for hosts without a
            // persistent Laravel worker. The next poll advances the next slice.
            $job = new ProcessCoachGhlSyncBatch($run->id, 5, false);
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            report($exception);

            CoachGhlSyncRun::query()->whereKey($run->id)->update([
                'status' => 'paused',
                'last_error' => $exception->getMessage(),
                'message' => 'Automatic processing paused after an unexpected web-request error.',
                'heartbeat_at' => now(),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function startSync(
        CoachGhlSyncPlanner $planner,
        CoachGhlBackgroundLauncher $launcher,
    ): void {
        $existing = CoachGhlSyncRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($existing) {
            $this->activeRunId = $existing->id;

            Notification::make()
                ->title('GHL synchronization is already active')
                ->body('Use Stop before starting a separate run.')
                ->info()
                ->send();

            return;
        }

        $planner->planForCoaches(
            Coach::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get(),
            reconcileAll: true,
        );

        $this->resetInterruptedTargets();
        $this->launchNewRun($launcher, false);
    }

    public function stopSync(): void
    {
        $run = $this->run;

        if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
            Notification::make()
                ->title('No active synchronization to stop')
                ->info()
                ->send();

            return;
        }

        $run->forceFill([
            'status' => 'cancelled',
            'message' => 'Synchronization stopped by an administrator.',
            'current_location_id' => null,
            'current_email' => null,
            'finished_at' => now(),
            'heartbeat_at' => now(),
        ])->save();

        CoachGhlSyncTarget::query()
            ->where('status', 'processing')
            ->update(['status' => 'pending']);

        SchoolGhlSyncTarget::query()
            ->where('status', 'processing')
            ->update(['status' => 'pending']);

        Notification::make()
            ->title('GHL synchronization stopped')
            ->body('Automatic processing stopped. Unfinished records remain pending.')
            ->warning()
            ->send();
    }

    public function restartSync(
        CoachGhlSyncPlanner $planner,
        CoachGhlBackgroundLauncher $launcher,
    ): void {
        $active = CoachGhlSyncRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($active) {
            $active->forceFill([
                'status' => 'cancelled',
                'message' => 'Stopped before restart.',
                'current_location_id' => null,
                'current_email' => null,
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ])->save();
        }

        // Rebuild the complete target set before restarting. This is important after
        // sport/gender changes because old all-to-all targets must never become pending again.
        $planner->planForCoaches(
            Coach::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get(),
            reconcileAll: true,
        );

        $this->resetInterruptedTargets();
        $this->launchNewRun($launcher, true);
    }

    protected function resetInterruptedTargets(): void
    {
        CoachGhlSyncTarget::query()
            ->whereIn('status', ['failed', 'processing'])
            ->update([
                'status' => 'pending',
                'last_error' => null,
            ]);

        SchoolGhlSyncTarget::query()
            ->whereIn('status', ['failed', 'processing'])
            ->update([
                'status' => 'pending',
                'last_error' => null,
            ]);
    }

    protected function launchNewRun(CoachGhlBackgroundLauncher $launcher, bool $restart): void
    {
        $total = CoachGhlSyncTarget::query()->where('status', 'pending')->count();

        if ($total === 0) {
            Notification::make()
                ->title('Nothing to synchronize')
                ->info()
                ->send();

            return;
        }

        $run = CoachGhlSyncRun::query()->create([
            'started_by' => auth()->id(),
            'status' => 'queued',
            'total' => $total,
            'account_groups' => CoachGhlSyncTarget::query()
                ->where('status', 'pending')
                ->distinct()
                ->count('location_id'),
            'message' => $restart
                ? 'Restarted with sport + gender scoped coach targets.'
                : 'Ready to sync sport + gender matched coaches to each GHL subaccount.',
            'heartbeat_at' => now(),
        ]);

        $launcher->launch($run);
        $this->activeRunId = $run->id;

        Notification::make()
            ->title($restart ? 'GHL synchronization restarted' : 'GHL synchronization queued')
            ->body('Only coaches matching the sport + gender audience of each GHL subaccount will be processed.')
            ->success()
            ->send();
    }

    public function getRunProperty(): ?CoachGhlSyncRun
    {
        return $this->activeRunId
            ? CoachGhlSyncRun::query()->find($this->activeRunId)
            : null;
    }

    public function getPendingCountProperty(): int
    {
        return CoachGhlSyncTarget::query()->where('status', 'pending')->count();
    }

    public function getErrorSummaryProperty(): array
    {
        // Group failures by the actual credential/subaccount that produced them.
        // Previously the UI grouped only by last_error, which hid which PLYRCARD
        // user/GHL location was responsible for a repeated failure.
        $groups = CoachGhlSyncTarget::query()
            ->where('status', 'failed')
            ->whereNotNull('last_error')
            ->selectRaw('MIN(id) as sample_id, last_error, location_id, representative_user_id, COUNT(*) as affected')
            ->groupBy('last_error', 'location_id', 'representative_user_id')
            ->orderByDesc('affected')
            ->limit(12)
            ->get();

        if ($groups->isEmpty()) {
            return [];
        }

        $samples = CoachGhlSyncTarget::query()
            ->with([
                'coach:id,school_id,display_name,email',
                'coach.school:id,name',
                'representativeUser:id,first_name,last_name,email,personal_email',
            ])
            ->whereIn('id', $groups->pluck('sample_id')->map(fn ($id): int => (int) $id))
            ->get()
            ->keyBy('id');

        $accountIds = $samples
            ->flatMap(function (CoachGhlSyncTarget $target): array {
                $ids = is_array($target->account_user_ids) ? $target->account_user_ids : [];
                if ($target->representative_user_id) {
                    $ids[] = (int) $target->representative_user_id;
                }

                return array_values(array_unique(array_filter(array_map('intval', $ids))));
            })
            ->unique()
            ->values();

        $accounts = User::query()
            ->withTrashed()
            ->select(['id', 'first_name', 'last_name', 'email', 'personal_email', 'ghl_location_id', 'deleted_at'])
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        return $groups
            ->map(function ($group) use ($samples, $accounts): array {
                /** @var CoachGhlSyncTarget|null $sample */
                $sample = $samples->get((int) $group->sample_id);
                $representativeId = $group->representative_user_id ? (int) $group->representative_user_id : null;
                $representative = $representativeId ? $accounts->get($representativeId) : null;

                $linkedIds = collect(is_array($sample?->account_user_ids) ? $sample->account_user_ids : [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->when($representativeId, fn ($ids) => $ids->push($representativeId))
                    ->unique()
                    ->values();

                $linkedAccounts = $linkedIds
                    ->map(function (int $id) use ($accounts): string {
                        $user = $accounts->get($id);
                        if (! $user) {
                            return 'Deleted user #' . $id;
                        }

                        return $this->syncAccountLabel($user);
                    })
                    ->all();

                $representativeLabel = $representative
                    ? $this->syncAccountLabel($representative)
                    : ($representativeId ? 'Deleted user #' . $representativeId : 'No representative user recorded');

                // The job's belongsTo relation excludes soft-deleted users, which means a
                // soft-deleted credential owner is operationally missing even though we can
                // still load the old account here to show the administrator exactly who it was.
                $representativeMissing = $representativeId !== null
                    && ($sample?->representativeUser === null);

                return [
                    'affected' => (int) $group->affected,
                    'message' => (string) $group->last_error,
                    'location_id' => trim((string) ($group->location_id ?? '')),
                    'representative_user_id' => $representativeId,
                    'representative' => $representativeLabel,
                    'representative_missing' => $representativeMissing,
                    'linked_accounts' => $linkedAccounts,
                    'coach_missing' => $sample !== null && $sample->coach === null,
                    'example_coach' => trim((string) ($sample?->coach_email_snapshot ?? '')),
                    'example_school' => trim((string) ($sample?->school_name_snapshot ?? $sample?->coach?->school?->name ?? '')),
                    'target_id' => $sample ? (int) $sample->getKey() : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function syncAccountLabel(User $user): string
    {
        $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
        $email = trim((string) ($user->email ?? $user->personal_email ?? ''));

        $parts = array_filter([
            $name !== '' ? $name : null,
            $email !== '' ? $email : null,
            'User #' . $user->getKey(),
            $user->trashed() ? 'soft-deleted' : null,
        ]);

        return implode(' · ', $parts);
    }

    public function render()
    {
        return view('livewire.coach-ghl-sync-panel');
    }
}