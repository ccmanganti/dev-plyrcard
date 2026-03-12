<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteTemplate extends Model
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
        return $this->hasMany(SiteTemplateField::class)->orderBy('sort_order');
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function heroTemplates(): BelongsToMany
    {
        return $this->belongsToMany(HeroTemplate::class, 'hero_template_site_template')
            ->withTimestamps();
    }

    public function supportsSport(?string $sport): bool
    {
        if (blank($sport)) {
            return false;
        }

        if (blank($this->sports)) {
            return true;
        }

        return in_array($sport, $this->sports, true);
    }

    public function getSportsLabelsAttribute(): array
    {
        return collect($this->sports ?? [])
            ->map(fn (string $sport) => str($sport)->replace('_', ' ')->title()->toString())
            ->values()
            ->all();
    }
}