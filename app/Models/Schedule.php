<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Schedule extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'opponent',
        'game_date',
        'game_time',
        'location',
        'venue',
        'status',
        'is_home',
        'result',
        'score',
        'notes',
    ];

    protected $casts = [
        'game_date' => 'date',
        'game_time' => 'datetime:H:i',
        'is_home' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}