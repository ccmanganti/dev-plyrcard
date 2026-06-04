<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    protected static function booted(): void
    {
        static::saving(function (Club $club) {
            if (blank($club->landing_page_slug) && filled($club->name)) {
                $club->landing_page_slug = static::uniqueLandingPageSlug($club->name, $club);
            }
        });
    }

    public static function uniqueLandingPageSlug(string $name, ?Club $club = null): string
    {
        $base = Str::slug($name) ?: 'club';
        $slug = $base;
        $counter = 2;

        while (
            static::query()
                ->where('landing_page_slug', $slug)
                ->when($club?->exists, fn ($query) => $query->whereKeyNot($club->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function landingUrl(): ?string
    {
        if (! $this->has_landing_page || ! $this->landing_page_is_published || blank($this->landing_page_slug)) {
            return null;
        }

        return route('clubs.landing', ['clubSlug' => $this->landing_page_slug]);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }


    public function referrals(): HasMany
    {
        return $this->hasMany(ClubReferral::class);
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

    public function clubLeagues(): HasMany
    {
        return $this->hasMany(ClubLeague::class);
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'club_leagues')
            ->withPivot([
                'id',
                'genders',
                'sport',
                'is_active',
                'sort_order',
                'legacy_club_ids',
                'settings',
            ])
            ->withTimestamps();
    }

    public function canonicalClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'canonical_club_id');
    }

    public function duplicateClubs(): HasMany
    {
        return $this->hasMany(Club::class, 'canonical_club_id');
    }
}