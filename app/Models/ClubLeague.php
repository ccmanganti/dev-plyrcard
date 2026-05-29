<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubLeague extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'club_id',
        'league_id',
        'genders',
        'sport',
        'is_active',
        'sort_order',
        'legacy_club_ids',
        'settings',
    ];

    protected $casts = [
        'genders' => 'array',
        'legacy_club_ids' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function supportsGender(?string $gender): bool
    {
        $gender = static::normalizeGender($gender);

        if (! $gender) {
            return true;
        }

        $genders = collect($this->genders ?? [])
            ->map(fn ($value) => static::normalizeGender($value))
            ->filter()
            ->values();

        return $genders->isEmpty() || $genders->contains($gender) || $genders->contains('coed');
    }

    public static function normalizeGender(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'female') || str_contains($value, 'girl') || str_contains($value, 'women') || str_contains($value, 'woman')) {
            return 'female';
        }

        if (str_contains($value, 'male') || str_contains($value, 'boy') || str_contains($value, 'men') || str_contains($value, 'man')) {
            return 'male';
        }

        if (str_contains($value, 'coed') || str_contains($value, 'mixed')) {
            return 'coed';
        }

        return in_array($value, ['male', 'female', 'coed'], true) ? $value : null;
    }
}
