<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
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
    use HasFactory, Notifiable, HasRoles, SoftDeletes, MustVerifyEmail;

    protected $fillable = [
        'first_name','last_name','gender','personal_email','email','phone','country','state','city','street','gpa','ncaa_field_id','year','birth','height','weight','jersey_number','sport','position','dominant_foot','academic_accolades','sports_accolades','natl_team_exp','team_name','team_id','ig_handle','x_handle','yt_url','press','parent','parent_email','parent_phone','sec_parent','sec_parent_email','sec_parent_phone','club_coach','club_coach_email','club_coach_phone','natl_coach','natl_coach_email','natl_coach_phone','tech_trainer','tech_trainer_email','tech_trainer_phone','snc_trainer','snc_trainer_email','snc_trainer_phone','school_id','club_id','league_id','club_league_id','pro_club_name','pro_club_logo','legacy_club_id','legacy_league_id','legacy_team_name','national_team_id','password','plyrcard_image','player_image','action_image','national_team_image','mobile_hero_image','youtube_thumbnail','raw_player_images','player_bio','featured_video_url','featured_video_urls','youtube_channel_id','youtube_uploads_playlist_id','youtube_cached_videos','youtube_cache_refreshed_at','national_team_period','max_speed','profile_completion_percentage','profile_completion_threshold_sent_at',
        'ghl_contact_id','ghl_location_id','ghl_api_key','total_emails_sent',
        'club_referral_id','registration_source','utm_club_id','utm_league_id','utm_team_name',
        'must_change_password','onboarding_completed_at','email_verification_sent_at',
    ];

    protected $hidden = ['password','remember_token','ghl_api_key'];

    protected static function booted(): void
    {
        static::saving(function ($user) {
            if ($user->isDirty('yt_url') || $user->isDirty('featured_video_urls')) {
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
            'email_verified_at' => 'datetime','email_verification_sent_at' => 'datetime','password' => 'hashed','natl_team_exp' => 'boolean','position' => 'array','youtube_cached_videos' => 'array','youtube_cache_refreshed_at' => 'datetime','raw_player_images' => 'array','club_referral_id' => 'integer','utm_club_id' => 'integer','utm_league_id' => 'integer','total_emails_sent' => 'integer','must_change_password' => 'boolean','onboarding_completed_at' => 'datetime',
        ];
    }

    public function clearYoutubeHighlightsCache(): void
    {
        $this->forceFill(['youtube_channel_id'=>null,'youtube_uploads_playlist_id'=>null,'youtube_cached_videos'=>null,'youtube_cache_refreshed_at'=>null])->save();
    }

    public function getFilamentName(): string { return trim($this->first_name . ' ' . $this->last_name); }
    public function nationalTeam(): BelongsTo { return $this->belongsTo(NationalTeam::class); }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function assignedClub(): BelongsTo { return $this->belongsTo(Club::class, 'club_id'); }
    public function legacyClub(): BelongsTo { return $this->belongsTo(Club::class, 'legacy_club_id'); }
    public function websites(): HasMany { return $this->hasMany(Website::class); }
    public function league(): BelongsTo { return $this->belongsTo(League::class); }
    public function legacyLeague(): BelongsTo { return $this->belongsTo(League::class, 'legacy_league_id'); }
    public function clubLeague(): BelongsTo { return $this->belongsTo(ClubLeague::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function activeWebsite(): HasOne { return $this->hasOne(Website::class)->where('is_active', true); }
    public function billingInformation(): HasOne { return $this->hasOne(BillingInformation::class); }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_user')->withTimestamps()->orderBy('game_date')->orderBy('game_time');
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by_user_id')->orderBy('game_date')->orderBy('game_time');
    }

    public function clubReferrals(): HasMany { return $this->hasMany(ClubReferral::class, 'club_manager_id'); }
    public function registrationReferral(): BelongsTo { return $this->belongsTo(ClubReferral::class, 'club_referral_id'); }
    public function favoriteSchoolRecords(): HasMany { return $this->hasMany(FavoriteSchool::class); }
    public function favoriteSchools(): BelongsToMany { return $this->belongsToMany(School::class, 'favorite_schools')->withTimestamps(); }
    public function recruitingLists(): HasMany { return $this->hasMany(MyList::class); }

    public function isClubManager(): bool
    {
        return method_exists($this, 'hasRole') && ($this->hasRole('Club Manager') || $this->hasRole('club manager') || $this->hasRole('ClubManager'));
    }

    public function canAccessPanel(Panel $panel): bool { return true; }

    public function isSuperadminOrImpersonating(): bool
    {
        if ($this->hasRole('superadmin')) return true;
        if (app('impersonate')->isImpersonating()) {
            $impersonatorId = app('impersonate')->getImpersonatorId();
            $impersonator = static::find($impersonatorId);
            return $impersonator?->hasRole('superadmin') ?? false;
        }
        return false;
    }

    public function shouldSeeOnboarding(): bool
    {
        return ! $this->isSuperadminOrImpersonating() && is_null($this->onboarding_completed_at);
    }

    public function hasGhlLocationId(): bool { return filled($this->ghl_location_id); }
    public function hasGhlApiKey(): bool { return filled($this->ghl_api_key); }
    public function hasGhlConnection(): bool { return $this->hasGhlLocationId() && $this->hasGhlApiKey(); }
    public function hasCompleteGhlConnection(): bool { return $this->hasGhlConnection(); }

    public function preferredGhlConnectionType(): ?string
    {
        if ($this->hasGhlLocationId() && $this->hasGhlApiKey()) return 'location_id_and_api_key';
        if ($this->hasGhlLocationId()) return 'location_id';
        if ($this->hasGhlApiKey()) return 'api_key';
        return null;
    }
}