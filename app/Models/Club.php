<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        // 'school_id',
        'league_id',
    ];

    // public function school(): BelongsTo
    // {
    //     return $this->belongsTo(School::class);
    // }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}