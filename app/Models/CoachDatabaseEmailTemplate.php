<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachDatabaseEmailTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'preview_text',
        'body_html',
        'graphic_url',
        'attachments',
        'is_sample',
        'is_locked',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_sample' => 'boolean',
        'is_locked' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}