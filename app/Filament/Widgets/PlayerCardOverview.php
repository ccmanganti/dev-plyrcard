<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PlayerCardOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = Auth::user();

        $totalViews = 0; // wire this later
        $cardScore = 0;  // wire this later

        $profileCompletion = $this->getProfileCompletion($user);
        $profileCompletionLabel = $this->getProfileCompletionLabel($profileCompletion);

        return [
            Stat::make('Card Views', number_format($totalViews))
                ->description('Total Views on Your PlyrCard')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->chart([0, 0, 0, 0, 0, 0, 0])
                ->color('danger'),

            Stat::make('Card Score', (string) $cardScore)
                ->description('Your PlyrCard performance score')
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->chart([0, 0, 0, 0, 0, 0, 0])
                ->color('warning'),

            Stat::make('Profile Complete', $profileCompletion . '%')
                ->description($profileCompletionLabel)
                ->descriptionIcon(Heroicon::OutlinedChartPie)
                ->chart([10, 20, 35, 45, 60, 75, $profileCompletion])
                ->color($this->getProfileCompletionColor($profileCompletion)),
        ];
    }

    protected function getProfileCompletion($user): int
    {
        if (! $user) {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Core fields
        |--------------------------------------------------------------------------
        | These are the fields that make sense for most athletes regardless of sport.
        | Sport-specific fields like dominant_foot are handled separately.
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Optional / sport-specific fields
        |--------------------------------------------------------------------------
        | These should not punish all athletes if not relevant to their sport.
        |--------------------------------------------------------------------------
        */
        $sportSpecificFields = [
            'position',
            'dominant_foot',
            'jersey_number',
            'max_speed',
            'natl_team_exp',
            'national_team_id',
            'national_team_period',
        ];

        $completedCore = 0;

        foreach ($coreFields as $field) {
            if ($this->hasValue($user->{$field} ?? null)) {
                $completedCore++;
            }
        }

        $corePercentage = count($coreFields) > 0
            ? ($completedCore / count($coreFields)) * 100
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Bonus score
        |--------------------------------------------------------------------------
        | Optional sport-specific fields can boost completion,
        | but not heavily punish players if not applicable.
        |--------------------------------------------------------------------------
        */
        $completedSportSpecific = 0;

        foreach ($sportSpecificFields as $field) {
            if ($this->hasValue($user->{$field} ?? null)) {
                $completedSportSpecific++;
            }
        }

        $sportBonus = count($sportSpecificFields) > 0
            ? ($completedSportSpecific / count($sportSpecificFields)) * 10
            : 0;

        $finalScore = min(100, round($corePercentage + $sportBonus));

        return (int) $finalScore;
    }

    protected function getProfileCompletionLabel(int $completion): string
    {
        return match (true) {
            $completion >= 100 => 'Outstanding — your PlyrCard is fully complete.',
            $completion >= 85 => 'Almost there — your PlyrCard is nearly complete.',
            $completion >= 60 => 'Great progress — keep building your PlyrCard.',
            $completion >= 30 => 'Good start — add more details to strengthen your PlyrCard.',
            default => 'Let’s get started — complete your PlyrCard to stand out.',
        };
    }

    protected function getProfileCompletionColor(int $completion): string
    {
        return match (true) {
            $completion >= 100 => 'success',
            $completion >= 70 => 'warning',
            default => 'danger',
        };
    }

    protected function hasValue(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => ! is_null($item) && $item !== '')) > 0;
        }

        return true;
    }
}