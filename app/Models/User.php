<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasName, FilamentUser
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'personal_email',
        'email',
        'phone',
        'country',
        'state',
        'city',
        'street',
        'gpa',
        'year',
        'birth',
        'height',
        'weight',
        'jersey_number',
        'sport',
        'position',
        'dominant_foot',
        'academic_accolades',
        'sports_accolades',
        'natl_team_exp',
        'team_name',
        'ig_handle',
        'x_handle',
        'yt_url',
        'press',
        'parent',
        'parent_email',
        'parent_phone',
        'sec_parent',
        'sec_parent_email',
        'sec_parent_phone',
        'club_coach',
        'club_coach_email',
        'club_coach_phone',
        'natl_coach',
        'natl_coach_email',
        'natl_coach_phone',
        'tech_trainer',
        'tech_trainer_email',
        'tech_trainer_phone',
        'snc_trainer',
        'snc_trainer_email',
        'snc_trainer_phone',
        'school_id',
        'club_id',
        'league_id',
        'national_team_id',
        'domain',
        'password',
        'plyrcard_image',
        'player_image',
        'mobile_hero_image',
        'youtube_thumbnail',
        'raw_player_images',
        'player_bio',
        'featured_video_url',
        'featured_video_urls',
        'youtube_channel_id',
        'youtube_uploads_playlist_id',
        'youtube_cached_videos',
        'youtube_cache_refreshed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::saving(function ($user) {
            if (
                $user->isDirty('yt_url') ||
                $user->isDirty('featured_video_urls')
            ) {
                $user->youtube_channel_id = null;
                $user->youtube_uploads_playlist_id = null;
                $user->youtube_cached_videos = null;
                $user->youtube_cache_refreshed_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'natl_team_exp' => 'boolean',
            'position' => 'array',
            'youtube_cached_videos' => 'array',
            'youtube_cache_refreshed_at' => 'datetime',
            'raw_player_images' => 'array',
        ];
    }

    public function clearYoutubeHighlightsCache(): void
    {
        $this->forceFill([
            'youtube_channel_id' => null,
            'youtube_uploads_playlist_id' => null,
            'youtube_cached_videos' => null,
            'youtube_cache_refreshed_at' => null,
        ])->save();
    }

    public function getFilamentName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function nationalTeam(): BelongsTo
    {
        return $this->belongsTo(NationalTeam::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function activeWebsite(): HasOne
    {
        return $this->hasOne(Website::class)->where('is_active', true);
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_user')
            ->withPivot([
                'will_come',
                'responded_at',
            ])
            ->withTimestamps()
            ->orderBy('game_date')
            ->orderBy('game_time');
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by_user_id')
            ->orderBy('game_date')
            ->orderBy('game_time');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}