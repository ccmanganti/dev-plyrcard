<?php

namespace App\Support;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ClubManagerAccess
{
    public static function isSuperadmin(?User $user): bool
    {
        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function isClubManager(?User $user): bool
    {
        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Club Manager')
                || $user->hasRole('club manager')
                || $user->hasRole('ClubManager')
            );
    }

    public static function canAccessClubArea(?User $user): bool
    {
        return static::isSuperadmin($user) || static::isClubManager($user);
    }

    public static function canAccessClubAdmin(?User $user): bool
    {
        return static::canAccessClubArea($user);
    }

    public static function assignedClubId(?User $user): ?int
    {
        return $user && filled($user->club_id) ? (int) $user->club_id : null;
    }

    public static function clubAdminClubIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $assignedClubId = static::assignedClubId($user);

        if (static::isClubManager($user)) {
            return $assignedClubId ? [$assignedClubId] : [];
        }

        if (static::isSuperadmin($user)) {
            return Club::query()
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    public static function managedClubIds(?User $user): array
    {
        return static::clubAdminClubIds($user);
    }

    public static function assignedClub(?User $user): ?Club
    {
        $clubIds = static::clubAdminClubIds($user);

        if (empty($clubIds)) {
            return null;
        }

        return Club::query()->whereKey($clubIds[0])->first();
    }

    public static function managedClubs(?User $user): Collection
    {
        $clubIds = static::clubAdminClubIds($user);

        if (empty($clubIds)) {
            return collect();
        }

        return Club::query()
            ->whereIn('id', $clubIds)
            ->orderBy('name')
            ->get();
    }

    public static function userCanAccessClub(?User $user, Club|int|null $club): bool
    {
        if (! $user || ! $club) {
            return false;
        }

        $clubId = $club instanceof Club ? (int) $club->getKey() : (int) $club;

        return in_array($clubId, static::clubAdminClubIds($user), true);
    }

    public static function scopeClubs(Builder $query, ?User $user): Builder
    {
        $clubIds = static::clubAdminClubIds($user);

        return empty($clubIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('id', $clubIds);
    }

    public static function scopePlayers(Builder $query, ?User $user): Builder
    {
        $clubIds = static::clubAdminClubIds($user);

        return empty($clubIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('club_id', $clubIds);
    }

    public static function canViewPlayer(?User $manager, User $player): bool
    {
        if (! $manager || ! filled($player->club_id)) {
            return false;
        }

        return in_array((int) $player->club_id, static::clubAdminClubIds($manager), true);
    }

    public static function playerDisplayName(User $player): string
    {
        return trim(($player->first_name ?? '') . ' ' . ($player->last_name ?? ''))
            ?: ($player->name ?? $player->email ?? 'Player');
    }

    public static function playerInitials(User $player): string
    {
        $name = static::playerDisplayName($player);

        return str($name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($part) => str($part)->substr(0, 1)->upper()->toString())
            ->implode('') ?: 'P';
    }

    public static function normalizeImageUrl(?string $candidate): ?string
    {
        if (blank($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return $candidate;
        }

        return Storage::disk('public')->url(ltrim($candidate, '/'));
    }

    /*
     |--------------------------------------------------------------------------
     | Player image helpers
     |--------------------------------------------------------------------------
     |
     | The dashboard Blade calls playerPlyrCardImageUrl() and
     | playerProfileImageUrl(). This hotfix adds both methods.
     |
     | PlyrCard images should render as full rectangular cards.
     | Profile-only images should render inside a circular avatar.
     |
     */

    public static function playerPlyrCardImageValue(User $player): ?string
    {
        foreach ([
            'plyrcard_image',
            'player_card_image',
            'plyr_card_image',
            'card_image',
            'generated_card_image',
            'generated_card',
            'share_card_image',
        ] as $field) {
            if (isset($player->{$field}) && filled($player->{$field})) {
                return (string) $player->{$field};
            }
        }

        return null;
    }

    public static function playerPlyrCardImageUrl(User $player): ?string
    {
        return static::normalizeImageUrl(static::playerPlyrCardImageValue($player));
    }

    public static function playerProfileImageValue(User $player): ?string
    {
        foreach ([
            'player_image',
            'profile_photo_path',
            'avatar',
            'photo',
            'headshot',
            'profile_image',
            'image',
            'action_image',
            'mobile_hero_image',
        ] as $field) {
            if (isset($player->{$field}) && filled($player->{$field})) {
                return (string) $player->{$field};
            }
        }

        if (is_array($player->raw_player_images ?? null)) {
            $candidate = collect($player->raw_player_images)
                ->flatten()
                ->filter()
                ->first(fn ($value) => is_string($value) && filled($value));

            if ($candidate) {
                return (string) $candidate;
            }
        }

        return null;
    }

    public static function playerProfileImageUrl(User $player): ?string
    {
        return static::normalizeImageUrl(static::playerProfileImageValue($player));
    }

    public static function playerImageUrl(User $player): ?string
    {
        return static::playerPlyrCardImageUrl($player)
            ?: static::playerProfileImageUrl($player);
    }

    public static function playerWebsiteUrl(User $player): ?string
    {
        $website = method_exists($player, 'websites')
            ? $player->websites()->where('is_published', true)->latest()->first()
            : null;

        if (! $website) {
            return null;
        }

        if (filled($website->domain)) {
            return str_starts_with($website->domain, 'http')
                ? $website->domain
                : 'https://' . $website->domain;
        }

        if (filled($website->slug)) {
            return url('/' . ltrim($website->slug, '/'));
        }

        return null;
    }

    public static function playerEmail(User $player): ?string
    {
        return $player->personal_email ?: $player->email;
    }

    public static function playerPhone(User $player): ?string
    {
        return $player->phone ?: ($player->mobile_phone ?? null);
    }
}