<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HeroTemplateField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hero_template_id',
        'name',
        'label',
        'type',
        'guide_image',
        'is_required',
        'sort_order',
        'options',
        'default_value',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'default_value' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(HeroTemplate::class, 'hero_template_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(WebsiteHeroFieldValue::class);
    }
}