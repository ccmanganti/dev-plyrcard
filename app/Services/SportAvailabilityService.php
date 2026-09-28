<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SportAvailabilityService
{
    public const SETTING_KEY = 'enabled_sports';
    private const CACHE_KEY = 'plyrcard:settings:enabled-sports';

    /**
     * Sports currently supported by athlete registration/profile position logic.
     * Keep this list aligned with the existing athlete position maps.
     */
    public const ATHLETE_OPTIONS = [
        'basketball' => 'Basketball',
        'volleyball' => 'Volleyball',
        'football' => 'Football',
        'baseball' => 'Baseball',
        'softball' => 'Softball',
        'soccer' => 'Soccer',
        'tennis' => 'Tennis',
        'badminton' => 'Badminton',
        'table_tennis' => 'Table Tennis',
        'track_and_field' => 'Track and Field',
        'swimming' => 'Swimming',
        'boxing' => 'Boxing',
        'martial_arts' => 'Martial Arts',
    ];

    /**
     * Sports currently supported by the local coach database.
     */
    public const COACH_OPTIONS = [
        'basketball' => 'Basketball',
        'volleyball' => 'Volleyball',
        'football' => 'Football',
        'baseball' => 'Baseball',
        'softball' => 'Softball',
        'soccer' => 'Soccer',
        'tennis' => 'Tennis',
        'badminton' => 'Badminton',
        'table_tennis' => 'Table Tennis',
        'track_and_field' => 'Track and Field',
        'swimming' => 'Swimming',
        'golf' => 'Golf',
        'lacrosse' => 'Lacrosse',
        'field_hockey' => 'Field Hockey',
        'ice_hockey' => 'Ice Hockey',
        'wrestling' => 'Wrestling',
        'cross_country' => 'Cross Country',
        'gymnastics' => 'Gymnastics',
        'water_polo' => 'Water Polo',
        'rowing' => 'Rowing',
        'bowling' => 'Bowling',
        'beach_volleyball' => 'Beach Volleyball',
        'fencing' => 'Fencing',
        'rugby' => 'Rugby',
        'boxing' => 'Boxing',
        'martial_arts' => 'Martial Arts',
        'other' => 'Other',
    ];

    public function allOptions(): array
    {
        return self::COACH_OPTIONS;
    }

    public function athleteBaseOptions(): array
    {
        return self::ATHLETE_OPTIONS;
    }

    public function coachBaseOptions(): array
    {
        return self::COACH_OPTIONS;
    }

    public function enabledKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): array {
            $allKeys = array_keys($this->allOptions());

            try {
                if (! Schema::hasTable('system_settings')) {
                    return $allKeys;
                }

                $setting = SystemSetting::query()
                    ->where('key', self::SETTING_KEY)
                    ->first();

                // No saved preference yet means every currently supported sport is enabled.
                if (! $setting) {
                    return $allKeys;
                }

                $saved = is_array($setting->value) ? $setting->value : [];

                return collect($saved)
                    ->map(fn ($value): string => strtolower(trim((string) $value)))
                    ->filter(fn (string $value): bool => array_key_exists($value, $this->allOptions()))
                    ->unique()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                // Settings should never make registration/profile/coach management unavailable.
                return $allKeys;
            }
        });
    }

    public function saveEnabledKeys(array $sports): void
    {
        $allowed = $this->allOptions();

        $sports = collect($sports)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => array_key_exists($value, $allowed))
            ->unique()
            ->values()
            ->all();

        SystemSetting::putValue(self::SETTING_KEY, $sports);
        Cache::forget(self::CACHE_KEY);
    }

    public function isEnabled(?string $sport): bool
    {
        $sport = strtolower(trim((string) $sport));

        return $sport !== '' && in_array($sport, $this->enabledKeys(), true);
    }

    public function athleteOptions(?string $includeCurrent = null): array
    {
        return $this->filterOptions($this->athleteBaseOptions(), $includeCurrent);
    }

    public function coachOptions(?string $includeCurrent = null): array
    {
        return $this->filterOptions($this->coachBaseOptions(), $includeCurrent);
    }

    /**
     * Keep a currently assigned disabled sport visible while editing an existing
     * record so disabling a sport never silently changes historical data.
     */
    public function filterOptions(array $baseOptions, ?string $includeCurrent = null): array
    {
        $enabled = array_flip($this->enabledKeys());
        $filtered = [];

        foreach ($baseOptions as $key => $label) {
            if (isset($enabled[$key])) {
                $filtered[$key] = $label;
            }
        }

        $includeCurrent = strtolower(trim((string) $includeCurrent));
        if ($includeCurrent !== '' && isset($baseOptions[$includeCurrent]) && ! isset($filtered[$includeCurrent])) {
            $filtered = [
                $includeCurrent => $baseOptions[$includeCurrent] . ' (currently assigned - disabled)',
            ] + $filtered;
        }

        return $filtered;
    }

    public function filterSportPositions(array $sportPositions): array
    {
        $enabled = array_flip($this->enabledKeys());

        return array_filter(
            $sportPositions,
            fn ($positions, $sport): bool => isset($enabled[(string) $sport]),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function availabilityRows(): array
    {
        $athleteKeys = array_flip(array_keys($this->athleteBaseOptions()));
        $enabledKeys = array_flip($this->enabledKeys());

        return collect($this->allOptions())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'enabled' => isset($enabledKeys[$key]),
                'athlete' => isset($athleteKeys[$key]),
                'coach' => true,
            ])
            ->values()
            ->all();
    }
}
