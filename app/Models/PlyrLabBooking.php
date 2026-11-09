<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlyrLabBooking extends Model
{
    use HasFactory;

    public const OPEN_STATUSES = ['pending', 'provisioning', 'awaiting_integration', 'active', 'provisioning_failed'];

    protected $fillable = [
        'player_user_id',
        'trainer_id',
        'scheduled_for',
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
}
