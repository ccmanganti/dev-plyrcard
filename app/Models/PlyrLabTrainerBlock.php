<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlyrLabTrainerBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'starts_at',
        'ends_at',
        'all_day',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(PlyrLabTrainer::class, 'trainer_id');
    }
}
