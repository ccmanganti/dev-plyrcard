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
        'league_id',
        'conference_id',
        'name',
        'logo',
        'hero_image',
        'background_image',
        'primary_color',
        'secondary_color',
        'city',
        'state',

        'has_landing_page',
        'landing_page_is_published',
        'landing_page_slug',
        'landing_page_intro',
        'landing_page_content',

        'contact_info',
        'coaching_staff',
        'sponsors_partners',
        'social_links',
        'branding',
    ];

    protected $casts = [
        'has_landing_page' => 'boolean',
        'landing_page_is_published' => 'boolean',

        'contact_info' => 'array',
        'coaching_staff' => 'array',
        'sponsors_partners' => 'array',
        'social_links' => 'array',
        'branding' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }
}