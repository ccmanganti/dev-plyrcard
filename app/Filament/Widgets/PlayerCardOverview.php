<?php

namespace App\Filament\Widgets;

use App\Services\GoHighLevelService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlayerCardOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = Auth::user();

        $profileCompletion = $this->getProfileCompletion($user);
        $profileCompletionLabel = $this->getProfileCompletionLabel($profileCompletion);

        $totalViews = $this->getGhlCommandCount($user, 'viewed_profile_contacts');

        $viewScore = min(30, (int) round((min($totalViews, 50) / 50) * 30));
        $completionScore = (int) round($profileCompletion * 0.70);
        $cardScore = min(100, $completionScore + $viewScore);

        return [
            Stat::make('Card Views', number_format($totalViews))
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('danger'),

            Stat::make('Highlight Views', (string) $cardScore)
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('warning'),

            Stat::make('Emails Delivered', (string) $cardScore)
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('warning'),

        ];
    }

    protected function getGhlCommandCount($user, string $command): int
    {
        if (! $user || ! method_exists($user, 'hasGhlConnection') || ! $user->hasGhlConnection()) {
            return 0;
        }

        if (! method_exists($user, 'hasGhlLocationId') || ! $user->hasGhlLocationId()) {
            return 0;
        }

        return Cache::remember(
            "user:{$user->id}:ghl-command:{$command}:count",
            now()->addMinutes(10),
            function () use ($user, $command): int {
                try {
                    $result = app(GoHighLevelService::class)->runDashboardCommand($user, $command);

                    return (int) ($result['count'] ?? 0);
                } catch (\Throwable $exception) {
                    Log::warning('Failed to pull GHL dashboard command count.', [
                        'user_id' => $user->id,
                        'command' => $command,
                        'message' => $exception->getMessage(),
                    ]);

                    return 0;
                }
            }
        );
    }

    protected function getProfileCompletion($user): int
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

        $completedCore = 0;

        foreach ($coreFields as $field) {
            if ($this->hasValue($user->{$field} ?? null)) {
                $completedCore++;
            }
        }

        $corePercentage = count($coreFields) > 0
            ? ($completedCore / count($coreFields)) * 100
            : 0;

        $completedSportSpecific = 0;

        foreach ($sportSpecificFields as $field) {
            if ($this->hasValue($user->{$field} ?? null)) {
                $completedSportSpecific++;
            }
        }

        $sportBonus = count($sportSpecificFields) > 0
            ? ($completedSportSpecific / count($sportSpecificFields)) * 10
            : 0;

        return (int) min(100, round($corePercentage + $sportBonus));
    }

    protected function getProfileCompletionLabel(int $completion): string
    {
        return match (true) {
            $completion >= 100 => 'Outstanding - your PlyrCard is fully complete.',
            $completion >= 85 => 'Almost there - your PlyrCard is nearly complete.',
            $completion >= 60 => 'Great progress - keep building your PlyrCard.',
            $completion >= 30 => 'Good start - add more details to strengthen your PlyrCard.',
            default => 'Let us get started - complete your PlyrCard to stand out.',
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