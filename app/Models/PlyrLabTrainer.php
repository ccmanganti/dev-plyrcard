<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlyrLabTrainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'headline',
        'location',
        'specialties',
        'bio',
        'booking_duration_minutes',
        'is_active',
        'plyomat_api_key',
        'plyomat_base_url',
        'plyomat_create_athlete_path',
        'plyomat_stats_path',
        'plyomat_auth_header',
        'plyomat_auth_prefix',
        'plyomat_account_id',
    ];

    protected $hidden = ['plyomat_api_key'];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'booking_duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'plyomat_api_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(PlyrLabBooking::class, 'trainer_id');
    }

    public function displayName(): string
    {
        return trim((string) ($this->display_name ?: $this->user?->getFilamentName())) ?: 'PLYRLab Trainer';
    }

    public function hasPlyomatConnection(): bool
    {
        return filled($this->plyomat_api_key)
            && filled($this->plyomat_base_url)
            && filled($this->plyomat_create_athlete_path)
            && filled($this->plyomat_stats_path);
    }
}
