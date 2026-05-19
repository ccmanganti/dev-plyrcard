<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}