<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Website extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',

        // GrapesJS
        'project_json',
        'html',
        'css',

        // Text content
        'aboutme_headline',
        'player_tagline',
        'player_bio',
        'highlights_headline',
        'highlights_tagline',
        'schedules_headline',
        'schedules_tagline',
        'acad_accolades_headline',
        'acad_accolades_tagline',
        'academic_accolades',
        'sport_accolades_headline',
        'sport_accolades_tagline',
        'sports_accolades',

        // Colors
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'surface_color',
        'text_primary_color',
        'text_secondary_color',

        // Embeds / assets
        'contact_form_embed',
        'yt_embed',
        'yt_playlist_embed',
        'logos',
        'highlights_thumbnail',
    ];

    protected $casts = [
        'logos' => 'array',
        'highlights_thumbnail' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
