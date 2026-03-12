<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'site_template_id',
        'hero_template_id',
        'name',
        'slug',
        'domain',
        'is_active',
        'is_published',
        'project_json',
        'html',
        'css',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'surface_color',
        'text_primary_color',
        'text_secondary_color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function siteTemplate(): BelongsTo
    {
        return $this->belongsTo(SiteTemplate::class);
    }

    public function heroTemplate(): BelongsTo
    {
        return $this->belongsTo(HeroTemplate::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(WebsiteFieldValue::class);
    }

    public function heroFieldValues(): HasMany
    {
        return $this->hasMany(WebsiteHeroFieldValue::class);
    }
}