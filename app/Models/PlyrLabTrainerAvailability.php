<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlyrLabTrainerAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'day_of_week',
        'is_available',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(PlyrLabTrainer::class, 'trainer_id');
    }
}
