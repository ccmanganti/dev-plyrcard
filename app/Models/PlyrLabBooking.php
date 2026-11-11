<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Notifications\PlyrLabTrainerBookedNotification;
use Throwable;

class PlyrLabBooking extends Model
{
    use HasFactory;

    public const OPEN_STATUSES = ['pending_approval', 'reschedule_pending', 'pending', 'awaiting_athlete_id', 'awaiting_integration', 'active', 'provisioning', 'provisioning_failed'];
    public const ACCESS_STATUSES = ['pending_approval', 'reschedule_pending', 'pending', 'awaiting_athlete_id', 'awaiting_integration', 'active', 'provisioning', 'provisioning_failed', 'completed'];

    protected $fillable = [
        'player_user_id',
        'trainer_id',
        'scheduled_for',
        'requested_scheduled_for',
        'status',
        'notes',
        'plyomat_athlete_id',
        'plyomat_provisioned_at',
        'plyomat_last_synced_at',
        'plyomat_stats',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'requested_scheduled_for' => 'datetime',
            'plyomat_provisioned_at' => 'datetime',
            'plyomat_last_synced_at' => 'datetime',
            'plyomat_stats' => 'array',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(PlyrLabTrainer::class, 'trainer_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function notifyStakeholders(string $event): void
    {
        $this->loadMissing(['player', 'trainer.user']);

        $targets = collect([
            $this->player,
            $this->trainer?->user,
        ])->filter();

        try {
            $admins = User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Superadmin', 'superadmin', 'Super Admin']))
                ->get();

            $targets = $targets->merge($admins);
        } catch (Throwable $exception) {
            report($exception);
        }

        $targets
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->each(function (User $user) use ($event): void {
                try {
                    $user->notify(new PlyrLabTrainerBookedNotification($this->getKey(), $event));
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }
}
