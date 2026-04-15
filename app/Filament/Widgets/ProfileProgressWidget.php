<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\Profiles\ProfileResource;

class ProfileProgressWidget extends Widget
{
    protected string $view = 'filament.widgets.profile-progress-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $user = Auth::user();

        $completion = $this->getProfileCompletion($user);
        $missingItems = $this->getMissingItems($user);
        $achievements = $this->getAchievements($completion);

        return [
            'completion' => $completion,
            'missingItems' => $missingItems,
            'achievements' => $achievements,
            'profileUrl' => ProfileResource::getUrl('index'),
        ];
    }

    protected function getProfileCompletion($user): int
    {
        if (! $user) {
            return 0;
        }

        $coreFields = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'birth' => 'Birth date',
            'gender' => 'Gender',
            'country' => 'Country',
            'city' => 'City',
            'sport' => 'Sport',
            'height' => 'Height',
            'weight' => 'Weight',
            'player_bio' => 'Player bio',
            'player_image' => 'Profile photo',
            'plyrcard_image' => 'PlyrCard image',
            'school_id' => 'School',
            'club_id' => 'Club',
            'league_id' => 'League',
            'featured_video_url' => 'Featured video',
            'ig_handle' => 'Instagram handle',
        ];

        $sportSpecificFields = [
            'position' => 'Position',
            'dominant_foot' => 'Dominant foot',
            'jersey_number' => 'Jersey number',
            'max_speed' => 'Max speed',
            'natl_team_exp' => 'National team experience',
            'national_team_id' => 'National team',
            'national_team_period' => 'National team period',
        ];

        $completedCore = collect($coreFields)
            ->filter(fn ($label, $field) => $this->hasValue($user->{$field} ?? null))
            ->count();

        $corePercentage = count($coreFields)
            ? ($completedCore / count($coreFields)) * 100
            : 0;

        $completedSportSpecific = collect($sportSpecificFields)
            ->filter(fn ($label, $field) => $this->hasValue($user->{$field} ?? null))
            ->count();

        $sportBonus = count($sportSpecificFields)
            ? ($completedSportSpecific / count($sportSpecificFields)) * 10
            : 0;

        return (int) min(100, round($corePercentage + $sportBonus));
    }

    protected function getMissingItems($user): array
    {
        if (! $user) {
            return [];
        }

        $fields = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'birth' => 'Birth date',
            'gender' => 'Gender',
            'country' => 'Country',
            'city' => 'City',
            'sport' => 'Sport',
            'height' => 'Height',
            'weight' => 'Weight',
            'player_bio' => 'Player bio',
            'player_image' => 'Profile photo',
            'plyrcard_image' => 'PlyrCard image',
            'school_id' => 'School',
            'club_id' => 'Club',
            'league_id' => 'League',
            'featured_video_url' => 'Featured video',
            'ig_handle' => 'Instagram handle',
            'position' => 'Position',
            'dominant_foot' => 'Dominant foot',
            'jersey_number' => 'Jersey number',
        ];

        return collect($fields)
            ->filter(fn ($label, $field) => ! $this->hasValue($user->{$field} ?? null))
            ->values()
            ->all();
    }

    protected function getAchievements(int $completion): array
    {
        $milestones = [
            ['label' => 'Starter', 'threshold' => 25],
            ['label' => 'Rising Talent', 'threshold' => 50],
            ['label' => 'Scouted Ready', 'threshold' => 75],
            ['label' => 'PlyrCard Complete', 'threshold' => 100],
        ];

        return collect($milestones)
            ->map(function (array $milestone) use ($completion): array {
                $unlocked = $completion >= $milestone['threshold'];

                return [
                    'label' => $milestone['label'],
                    'threshold' => $milestone['threshold'],
                    'unlocked' => $unlocked,
                ];
            })
            ->all();
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
            return count(array_filter(
                $value,
                fn ($item) => ! is_null($item) && $item !== ''
            )) > 0;
        }

        return true;
    }
}