<?php

namespace App\Livewire;

use App\Models\Coach;
use App\Models\CoachGhlSyncRun;
use App\Models\CoachGhlSyncTarget;
use App\Models\SchoolGhlSyncTarget;
use App\Services\CoachGhlBackgroundLauncher;
use App\Services\CoachGhlSyncPlanner;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CoachGhlSyncPanel extends Component
{
    public ?int $activeRunId = null;

    public function mount(): void
    {
        $this->activeRunId = CoachGhlSyncRun::query()->latest('id')->value('id');
    }

    public function startSync(CoachGhlSyncPlanner $planner, CoachGhlBackgroundLauncher $launcher): void
    {
        $existing = CoachGhlSyncRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($existing) {
            $this->activeRunId = $existing->id;
            Notification::make()->title('GHL synchronization is already running')->info()->send();
            return;
        }

        $planner->planForCoaches(
            Coach::query()->whereNotNull('email')->where('email', '!=', '')->get()
        );

        $this->queueTargetsForRestart();
        $this->launchNewRun($launcher, false);
    }

    public function stopSync(): void
    {
        $run = $this->run;
        if (! $run || ! in_array($run->status, ['queued', 'running'], true)) {
            Notification::make()->title('No active synchronization to stop')->info()->send();
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

        CoachGhlSyncTarget::query()->where('status', 'processing')->update(['status' => 'pending']);
        SchoolGhlSyncTarget::query()->where('status', 'processing')->update(['status' => 'pending']);

        Notification::make()
            ->title('GHL synchronization stopped')
            ->body('The current worker will exit safely. Unfinished records remain pending.')
            ->warning()
            ->send();
    }

    public function restartSync(CoachGhlBackgroundLauncher $launcher): void
    {
        $active = CoachGhlSyncRun::query()->whereIn('status', ['queued', 'running'])->latest('id')->first();
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

        $this->queueTargetsForRestart();
        $this->launchNewRun($launcher, true);
    }

    public function retryFailed(CoachGhlBackgroundLauncher $launcher): void
    {
        $this->restartSync($launcher);
    }

    protected function queueTargetsForRestart(): void
    {
        CoachGhlSyncTarget::query()
            ->whereIn('status', ['failed', 'processing'])
            ->update(['status' => 'pending', 'last_error' => null]);

        SchoolGhlSyncTarget::query()
            ->whereIn('status', ['failed', 'processing'])
            ->update(['status' => 'pending', 'last_error' => null]);
    }

    protected function launchNewRun(CoachGhlBackgroundLauncher $launcher, bool $restart): void
    {
        $total = CoachGhlSyncTarget::query()->where('status', 'pending')->count();
        if ($total === 0) {
            Notification::make()->title('Nothing to synchronize')->info()->send();
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
                ? 'Restarting pending school and coach synchronization…'
                : 'Preparing school and coach synchronization…',
            'heartbeat_at' => now(),
        ]);

        $launch = $launcher->launch($run);
        $this->activeRunId = $run->id;

        Notification::make()
            ->title($restart ? 'GHL synchronization restarted' : 'GHL synchronization started')
            ->body(($launch['started'] ?? false)
                ? 'The process is running in the background and continues after page reloads.'
                : 'The run was queued, but the detached worker could not start.')
            ->success()
            ->send();
    }

    public function getRunProperty(): ?CoachGhlSyncRun
    {
        return $this->activeRunId ? CoachGhlSyncRun::query()->find($this->activeRunId) : null;
    }

    public function getPendingCountProperty(): int
    {
        return CoachGhlSyncTarget::query()->where('status', 'pending')->count();
    }

    public function getFailedCountProperty(): int
    {
        return CoachGhlSyncTarget::query()->where('status', 'failed')->count();
    }

    public function getFailedSchoolCountProperty(): int
    {
        return SchoolGhlSyncTarget::query()->where('status', 'failed')->count();
    }

    public function getErrorSummaryProperty(): array
    {
        return CoachGhlSyncTarget::query()
            ->where('status', 'failed')
            ->whereNotNull('last_error')
            ->selectRaw('last_error, COUNT(*) as affected')
            ->groupBy('last_error')
            ->orderByDesc('affected')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'affected' => (int) $row->affected,
                'message' => (string) $row->last_error,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.coach-ghl-sync-panel');
    }
}