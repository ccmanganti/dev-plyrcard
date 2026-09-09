<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Coach extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'first_name', 'last_name', 'display_name', 'email', 'secondary_email',
        'phone', 'title', 'sport', 'gender', 'division', 'conference', 'verification_status',
        'confidence_level', 'audit_notes', 'city', 'state', 'country', 'website_url',
        'notes', 'ghl_contact_id', 'ghl_location_id', 'ghl_synced_at', 'ghl_sync_status',
        'is_active', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return ['ghl_synced_at' => 'datetime', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Coach $coach): void {
            $coach->first_name = trim((string) $coach->first_name);
            $coach->last_name = trim((string) $coach->last_name);
            $coach->display_name = trim($coach->first_name . ' ' . $coach->last_name);
            $coach->email = filled($coach->email) ? Str::lower(trim((string) $coach->email)) : null;
            $coach->secondary_email = filled($coach->secondary_email) ? Str::lower(trim((string) $coach->secondary_email)) : null;
            $coach->gender = static::normalizeGender($coach->gender);
        });

        // Normal model saves should invalidate the shared Recruiting Center catalog
        // immediately. Spreadsheet upserts bypass Eloquent events, so the importer also
        // invalidates this fingerprint explicitly after each batch.
        $forgetRecruitingCatalog = static function (): void {
            Cache::forget('recruiting:local-catalog-fingerprint:v132');
        };
        static::saved($forgetRecruitingCatalog);
        static::deleted($forgetRecruitingCatalog);
        static::restored($forgetRecruitingCatalog);
    }

    public static function genderOptions(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
        ];
    }

    public static function normalizeGender(mixed $gender): ?string
    {
        $value = Str::of((string) $gender)
            ->lower()
            ->replace(["'", '’', '_', '-'], ' ')
            ->squish()
            ->toString();

        if ($value === '') {
            return null;
        }

        if (in_array($value, ['female', 'f', 'woman', 'women', 'womens', "women s", 'girl', 'girls'], true)) {
            return 'female';
        }

        if (in_array($value, ['male', 'm', 'man', 'men', 'mens', "men s", 'boy', 'boys'], true)) {
            return 'male';
        }

        return null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForSport(Builder $query, ?string $sport): Builder
    {
        return $query->when(filled($sport), fn (Builder $query): Builder => $query->where('sport', $sport));
    }

    public function scopeForGender(Builder $query, mixed $gender): Builder
    {
        $gender = static::normalizeGender($gender);

        return $gender
            ? $query->where('gender', $gender)
            : $query->whereRaw('1 = 0');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name . ' ' . $this->last_name));
    }
}