<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlyrPointServiceSample extends Model
{
    protected $fillable = [
        'uuid',
        'plyr_point_service_id',
        'type',
        'title',
        'path',
        'source_url',
        'youtube_id',
        'mime',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlyrPointService::class, 'plyr_point_service_id');
    }
}
