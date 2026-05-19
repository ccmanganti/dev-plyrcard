<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'logo',
        'hero_image',
        'background_image',

        'has_landing_page',
        'landing_page_is_published',
        'landing_page_slug',
        'landing_page_intro',
        'landing_page_content',

        'coaching_staff',
        'team_settings',
        'branding',
    ];

    protected $casts = [
        'has_landing_page' => 'boolean',
        'landing_page_is_published' => 'boolean',

        'coaching_staff' => 'array',
        'team_settings' => 'array',
        'branding' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Team $team) {
            if (blank($team->landing_page_slug) && filled($team->name)) {
                $team->landing_page_slug = static::uniqueLandingPageSlug($team->name, $team);
            }
        });
    }

    public static function uniqueLandingPageSlug(string $name, ?Team $team = null): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $counter = 2;

        while (
            static::query()
                ->where('club_id', $team?->club_id)
                ->where('landing_page_slug', $slug)
                ->when($team?->exists, fn ($query) => $query->whereKeyNot($team->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function landingGenderSegment(): string
    {
        $settings = is_array($this->team_settings) ? $this->team_settings : [];

        $rawGender = strtolower((string) (
            $settings['gender']
            ?? $settings['division_gender']
            ?? $this->club?->league?->gender
            ?? ''
        ));

        $name = strtolower((string) $this->name);

        if (
            str_contains($rawGender, 'female')
            || str_contains($rawGender, 'women')
            || str_contains($rawGender, 'woman')
            || str_contains($rawGender, 'girls')
            || str_contains($rawGender, 'girl')
            || str_contains($name, 'women')
            || str_contains($name, 'woman')
            || str_contains($name, 'girls')
            || str_contains($name, 'girl')
            || str_contains($name, 'female')
        ) {
            return 'womens';
        }

        return 'mens';
    }

    public function landingUrl(): ?string
    {
        if (
            ! $this->has_landing_page
            || ! $this->landing_page_is_published
            || blank($this->landing_page_slug)
            || ! $this->club
            || blank($this->club->landing_page_slug)
        ) {
            return null;
        }

        return route('clubs.teams.landing', [
            'clubSlug' => $this->club->landing_page_slug,
            'gender' => $this->landingGenderSegment(),
            'teamSlug' => $this->landing_page_slug,
        ]);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}