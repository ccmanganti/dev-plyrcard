<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteFieldValue extends Model
{
    protected $fillable = [
        'website_id',
        'site_template_field_id',
        'value',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function templateField(): BelongsTo
    {
        return $this->belongsTo(SiteTemplateField::class, 'site_template_field_id');
    }
}