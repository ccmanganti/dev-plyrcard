<?php

namespace App\Services;

use App\Models\User;

class ProfileCompletionService
{
    public function calculate(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $coreFields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'birth',
            'gender',
            'country',
            'city',
            'sport',
            'height',
            'weight',
            'player_bio',
            'player_image',
            'plyrcard_image',
            'school_id',
            'club_id',
            'league_id',
            'featured_video_url',
            'ig_handle',
        ];

        $sportSpecificFields = [
            'position',
            'dominant_foot',
            'jersey_number',
            'max_speed',
            'natl_team_exp',
            'national_team_id',
            'national_team_period',
        ];

        $completedCore = collect($coreFields)
            ->filter(fn ($field) => $this->hasValue($user->{$field} ?? null))
            ->count();

        $corePercentage = count($coreFields)
            ? ($completedCore / count($coreFields)) * 100
            : 0;

        $completedSportSpecific = collect($sportSpecificFields)
            ->filter(fn ($field) => $this->hasValue($user->{$field} ?? null))
            ->count();

        $sportBonus = count($sportSpecificFields)
            ? ($completedSportSpecific / count($sportSpecificFields)) * 10
            : 0;

        return (int) min(100, round($corePercentage + $sportBonus));
    }

    private function hasValue(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count(array_filter(
                $value,
                fn ($item) => ! is_null($item) && $item !== ''
            )) > 0;
        }

        return true;
    }
}