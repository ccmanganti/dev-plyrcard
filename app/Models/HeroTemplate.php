<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HeroTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'blade_view',
        'sports',
        'preview_image',
        'description',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sports' => 'array',
        'settings' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(HeroTemplateField::class)->orderBy('sort_order');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function siteTemplates(): BelongsToMany
    {
        return $this->belongsToMany(SiteTemplate::class, 'hero_template_site_template')
            ->withTimestamps();
    }
}