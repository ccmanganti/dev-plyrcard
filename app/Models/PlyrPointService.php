<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlyrPointService extends Model
{
    protected $fillable = [
        'key',
        'name',
        'points',
        'description',
        'cta',
        'inclusions',
        'is_enabled',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'points' => 'integer',
        'inclusions' => 'array',
        'is_enabled' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function samples(): HasMany
    {
        return $this->hasMany(PlyrPointServiceSample::class)->orderBy('sort_order')->orderBy('id');
    }
}
