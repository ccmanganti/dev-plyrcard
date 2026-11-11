<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\PlyrLabBookingNotificationService;

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

    public function notifyStakeholders(string $event): array
    {
        return app(PlyrLabBookingNotificationService::class)->send($this, $event);
    }
}
