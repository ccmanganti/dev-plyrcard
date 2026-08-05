<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachGhlSyncRun extends Model
{
    protected $fillable = [
        'started_by', 'status', 'total', 'processed', 'created_count',
        'updated_count', 'unchanged_count', 'failed_count', 'account_groups',
        'school_created_count', 'school_updated_count', 'school_unchanged_count',
        'school_failed_count', 'current_location_id', 'current_email', 'message',
        'last_error', 'started_at', 'heartbeat_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function progressPercent(): int
    {
        if ($this->total <= 0) {
            return in_array($this->status, ['completed', 'completed_with_errors'], true) ? 100 : 0;
        }

        return min(100, (int) floor(($this->processed / $this->total) * 100));
    }
}