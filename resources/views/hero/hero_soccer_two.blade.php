@php
    $user = $website->user;

    $primary   = $website->primary_color ?: '#ef4444';
    $secondary = $website->secondary_color ?: '#111111';
    $accent    = $website->accent_color ?: '#ffffff';
    $bg        = $website->background_color ?: '#0b0b0b';
    $surface   = $website->surface_color ?: '#171717';
    $text1     = $website->text_primary_color ?: '#ffffff';
    $text2     = $website->text_secondary_color ?: '#d4d4d8';

    $heroFieldValues = $website->relationLoaded('heroFieldValues')
        ? $website->heroFieldValues
        : $website->heroFieldValues()->with('templateField')->get();

    $getHeroFieldRecord = function (string $fieldName) use ($heroFieldValues) {
        return $heroFieldValues->first(function ($item) use ($fieldName) {
            return optional($item->templateField)->name === $fieldName;
        });
    };

    $getHeroFieldValue = function (string $fieldName, $default = null) use ($getHeroFieldRecord) {
        $record = $getHeroFieldRecord($fieldName);
        return $record?->value ?? $default;
    };

    $resolveMediaUrl = function ($raw, $fallback = '') {
        if (blank($raw)) {
            return $fallback;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                return $trimmed;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                return asset('storage/' . ltrim($trimmed, '/'));
            }
        }

        if (is_array($raw)) {
            if (isset($raw[0])) {
                $first = $raw[0];

                if (is_string($first)) {
                    return filter_var($first, FILTER_VALIDATE_URL)
                        ? $first
                        : asset('storage/' . ltrim($first, '/'));
                }

                if (is_array($first)) {
                    $path = $first['url'] ?? $first['path'] ?? $first['image_url'] ?? null;
                    if ($path) {
                        return filter_var($path, FILTER_VALIDATE_URL)
                            ? $path
                            : asset('storage/' . ltrim($path, '/'));
                    }
                }
            }

            $path = $raw['url'] ?? $raw['path'] ?? $raw['image_url'] ?? null;
            if ($path) {
                return filter_var($path, FILTER_VALIDATE_URL)
                    ? $path
                    : asset('storage/' . ltrim($path, '/'));
            }
        }

        return $fallback;
    };

    $normalizeDisplayValue = function ($value, $separator = ' / ') {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return implode($separator, array_filter(array_map(function ($item) {
                return is_scalar($item) ? (string) $item : '';
            }, $value)));
        }

        return filled($value) ? (string) $value : '';
    };

    $formatCoachDisplay = function ($value) use ($normalizeDisplayValue) {
        $fullName = trim($normalizeDisplayValue($value));

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $lastName = end($parts) ?: $fullName;

        return 'Coach ' . $lastName;
    };

    $formatPositionDisplay = function ($value) use ($normalizeDisplayValue) {
        $position = $normalizeDisplayValue($value);

        if ($position === '') {
            return '';
        }

        return collect(explode(' / ', $position))
            ->map(fn ($item) => str($item)->replace('_', ' ')->title()->toString())
            ->implode(' / ');
    };

    $formatGpaDisplay = function ($value) use ($normalizeDisplayValue) {
        $gpa = trim($normalizeDisplayValue($value));

        if ($gpa === '') {
            return '';
        }

        if (is_numeric($gpa)) {
            return number_format((float) $gpa, 1, '.', '');
        }

        return $gpa;
    };

    $firstLine = function ($value) use ($normalizeDisplayValue) {
        $text = trim($normalizeDisplayValue($value, "\n"));

        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }

        return '';
    };

    $splitAccolades = function ($value) use ($normalizeDisplayValue) {
        $text = trim($normalizeDisplayValue($value, "\n"));

        if ($text === '') {
            return collect();
        }

        $text = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "’", "‘", "“", "”"],
            ["'", "'", '"', '"', "'", "'", '"', '"'],
            $text
        );

        return collect(preg_split('/\r\n|\r|\n|[•|;]+/', $text) ?: [])
            ->flatMap(function ($item) {
                return preg_split('/\s*,\s*/', (string) $item) ?: [];
            })
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();
    };
    $playerFullName = trim($getHeroFieldValue('hero_player_name', ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));

    $nameParts = preg_split('/\s+/', trim($playerFullName)) ?: [];
    $firstName = strtoupper($nameParts[0] ?? '');
    $lastName  = strtoupper(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '');

    $plyrCardImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_plyrcard_image', $user?->plyrcard_image),
        ''
    );

    $playerImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_image', $user?->player_image),
        ''
    );

    $playerActionImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_action_image'),
        ''
    );

    $playerNationalImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_player_national_image'),
        ''
    );

    $mobileHeroImageUrl = $resolveMediaUrl(
        $user?->mobile_hero_image,
        ''
    );

    $bottomTeamImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_bottom_team_image'), '');

    $defaultBackgroundImageUrl = asset('hero_images/hero_one/background.png');

    $backgroundImageUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_background_image'),
        $defaultBackgroundImageUrl
    );

    $sportRaw = $normalizeDisplayValue(
        $getHeroFieldValue(
            'hero_sport',
            $user?->sport?->name
                ?? $user?->sport
                ?? $website?->sport
                ?? $website?->sports
                ?? ''
        )
    );

    $sportKey = str($sportRaw)
        ->lower()
        ->trim()
        ->replace('&', 'and')
        ->replace('-', '_')
        ->replace(' ', '_')
        ->replace('__', '_')
        ->toString();

    $sportBallMap = [
        'basketball' => 'basketball.png',
        'football' => 'football.png',
        'american_football' => 'football.png',
        'baseball' => 'baseball.png',
        'softball' => 'softball.png',
        'soccer' => 'soccer.png',
        'futbol' => 'soccer.png',
        'volleyball' => 'volleyball.png',
        'tennis' => 'tennis.png',
        'golf' => 'golf.png',
        'lacrosse' => 'lacrosse.png',
    ];

    $defaultBallLogoUrl = isset($sportBallMap[$sportKey])
        ? asset('hero_images/hero_one/' . $sportBallMap[$sportKey])
        : '';

    $ballLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_ball_logo'),
        $defaultBallLogoUrl
    );

    $bgJerseyNumber = $getHeroFieldValue('hero_bg_jersey_number', $user?->jersey_number ?? '');

    $positionDisplay = $formatPositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? ''));

    $hometown = $normalizeDisplayValue($getHeroFieldValue('hero_stat_hometown', ''));
    if ($hometown === '') {
        $hometown = collect([
            $user?->city,
            $user?->state,
        ])
            ->filter(fn ($value) => filled($value))
            ->implode(', ');
    }

    $stats = [
        'GPA' => $formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? '')),
        'DOB' => filled($getHeroFieldValue('hero_stat_dob', $user?->birth ?? ''))
            ? \Carbon\Carbon::parse($getHeroFieldValue('hero_stat_dob', $user?->birth ?? ''))->format('F Y')
            : '',
        'Hometown' => $hometown,
        'International' => $normalizeDisplayValue(
            $getHeroFieldValue(
                'hero_stat_international',
                $user?->natl_team_exp ? ($user?->national_team_name ?? '') : ''
            )
        ),
        'League' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_league', $user?->club->league?->name ?? '')),
        'High School' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? '')),
        'Height' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? '')),
        'Weight' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? '')),
        'Class' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? '')),
        'Coach' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
        'Championship' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_championship', '')),
    ];

    /*
    |--------------------------------------------------------------------------
    | Mobile-specific derived values
    |--------------------------------------------------------------------------
    */
    $mobileNameLine = trim($firstName . ' ' . (filled($bgJerseyNumber) ? '#' . $bgJerseyNumber : ''));

    $mobileClass = $stats['Class'] ?? '';
    $mobileGpa = $stats['GPA'] ?? '';
    $mobileDob = $stats['DOB'] ?? '';
    $mobileHeight = $stats['Height'] ?? '';
    $mobileLeague = $stats['League'] ?? '';

    $mobileInternational = $normalizeDisplayValue(
        $getHeroFieldValue(
            'hero_stat_international',
            $user?->national_team_name ?? ''
        )
    );

    $mobileClub = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_club', $user?->club?->name ?? '')
    );

    $mobileDominantFoot = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_dominant_foot', $user?->dominant_foot ?? '')
    );

    $mobileClubLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_club_logo', $user?->club?->logo ?? ''),
        ''
    );

    $mobileLeagueLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_league_logo', $user?->club?->league?->logo ?? ''),
        ''
    );

    $mobileNationalLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_national_logo', $user?->national_team_logo ?? ''),
        ''
    );

    $sportsAccoladesRaw = trim($normalizeDisplayValue(
        $getHeroFieldValue('hero_sports_accolades', $user?->sports_accolades ?? '')
    ));

    $academicAccoladesRaw = trim($normalizeDisplayValue(
        $getHeroFieldValue('hero_academic_accolades', $user?->academic_accolades ?? '')
    ));

    $fallbackSportsAccolade = $firstLine($sportsAccoladesRaw);
    $fallbackAcademicAccolade = $firstLine($academicAccoladesRaw);

    $desktopSportsAccolades = $splitAccolades(
        $getHeroFieldValue('hero_sports_accolades', $user?->sports_accolades ?? '')
    );

    $desktopAcademicAccolades = $splitAccolades(
        $getHeroFieldValue('hero_academic_accolades', $user?->academic_accolades ?? '')
    );

    $desktopAccolades = collect();

    $desktopNationalTeamName = trim((string) ($user?->national_team_name ?? ''));

    if ($user?->natl_team_exp && $desktopNationalTeamName !== '') {
        $desktopAccolades->push([
            'text' => $desktopNationalTeamName,
            'icon' => filled($mobileNationalLogoUrl) ? $mobileNationalLogoUrl : null,
            'is_national' => true,
        ]);
    }

    foreach ($desktopSportsAccolades as $accolade) {
        $desktopAccolades->push([
            'text' => $accolade,
            'icon' => null,
            'is_national' => false,
        ]);
    }

    if ($desktopSportsAccolades->isEmpty()) {
        foreach ($desktopAcademicAccolades as $accolade) {
            $desktopAccolades->push([
                'text' => $accolade,
                'icon' => null,
                'is_national' => false,
            ]);
        }
    }

    $desktopAccolades = $desktopAccolades
        ->filter(fn ($item) => filled($item['text'] ?? ''))
        ->unique(fn ($item) => mb_strtolower(trim((string) ($item['text'] ?? ''))))
        ->take(3)
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Mobile accolades
    |--------------------------------------------------------------------------
    | Left panel  = priority accolade
    | Right panel = next distinct accolade
    */
    $rightMobileAccoladeTitle = '';
    $rightMobileAccoladeImageUrl = '';

    $leftMobileAccoladeTitle = '';
    $leftMobileAccoladeSubtitle = '';
    $leftMobileAccoladeImageUrl = '';

    $accoladeCandidates = collect([
        [
            'key' => 'national_team',
            'title' => ($user?->natl_team_exp && filled($user?->national_team_name))
                ? trim((string) $user->national_team_name)
                : '',
        ],
        [
            'key' => 'sports',
            'title' => $fallbackSportsAccolade,
        ],
        [
            'key' => 'academic',
            'title' => $fallbackAcademicAccolade,
        ],
    ])->filter(fn ($item) => filled($item['title']))->values();

    $primaryAccolade = $accoladeCandidates->get(0);
    $secondaryAccolade = $accoladeCandidates->get(1);

    /*
    |--------------------------------------------------------------------------
    | LEFT = priority accolade
    |--------------------------------------------------------------------------
    */
    if ($primaryAccolade) {
        $leftMobileAccoladeTitle = $primaryAccolade['title'];
    } elseif (filled($mobileInternational)) {
        $leftMobileAccoladeTitle = $mobileInternational;
    }

    if ($primaryAccolade && $primaryAccolade['key'] === 'national_team') {
        $leftMobileAccoladeSubtitle = 'National Team';
    }

    if ($primaryAccolade && $primaryAccolade['key'] === 'national_team' && filled($mobileNationalLogoUrl)) {
        $leftMobileAccoladeImageUrl = $mobileNationalLogoUrl;
    } elseif (filled($mobileLeagueLogoUrl)) {
        $leftMobileAccoladeImageUrl = $mobileLeagueLogoUrl;
    }

    /*
    |--------------------------------------------------------------------------
    | RIGHT = next distinct accolade
    |--------------------------------------------------------------------------
    */
    if ($secondaryAccolade) {
        $rightMobileAccoladeTitle = $secondaryAccolade['title'];
    }

    if ($secondaryAccolade && $secondaryAccolade['key'] === 'national_team' && filled($mobileNationalLogoUrl)) {
        $rightMobileAccoladeImageUrl = $mobileNationalLogoUrl;
    } elseif (filled($mobileLeagueLogoUrl)) {
        $rightMobileAccoladeImageUrl = $mobileLeagueLogoUrl;
    }

    $mobileMainImage = $playerImageUrl ?: $backgroundImageUrl;
    $hasMobileHeroOverride = filled($mobileHeroImageUrl);
@endphp

<style>
    .font-antonio {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .font-iceberg {
        font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .hero-desktop {
        display: block;
    }

    .hero-mobile-fallback {
        display: none;
    }

    .hero-layered-stack img {
        user-select: none;
        -webkit-user-drag: none;
    }

    .hero-player-card {
        width: 200px !important;
        max-width: 200px !important;
        height: auto !important;
        flex: 0 0 200px !important;
    }

    .hero-right-content {
        width: 100%;
        max-width: 760px;
        margin-left: auto;
        padding-top: 1.5rem;
    }

    .hero-name-and-card {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        position: relative;
    }

    .hero-name-block {
        margin: 0 !important;
        padding: 0 !important;
        min-width: 0;
    }

    .hero-position-line {
        margin-top: 4px;
        margin-bottom: 10px;
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 300;
        font-size: 22px;
        line-height: 0.95;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.92);
    }

    .hero-card-stack {
        position: relative;
        width: 200px;
        max-width: 200px;
        flex: 0 0 200px;
        display: flex;
        justify-content: flex-end;
    }

    .hero-card-stack-inner {
        position: relative;
        width: 100%;
    }

    .hero-stats-block {
        margin-left: 0 !important;
        padding-left: 0 !important;
        max-width: 610px;
    }

    .hero-stats-grid {
        display: grid;
        grid-template-columns: minmax(120px, 170px) minmax(0, 1fr);
        column-gap: 1.1rem;
        row-gap: 0.45rem;
    }

.hero-accolades-list {
    margin-top: 20px;
    display: grid;
    gap: 0.5rem;
    width: 100%;
    max-width: 100%;
}

.hero-accolade-row {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    width: 100%;
    max-width: 100%;
}

.hero-accolade-icon-wrap {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    flex: 0 0 auto;
    padding-top: 2px;
}

.hero-accolade-icon {
    width: 34px;
    height: 34px;
    object-fit: contain;
    display: block;
    flex: 0 0 34px;
}

.hero-accolade-icon--trophy {
    color: {{ $primary }};
}

.hero-accolade-text {
    min-width: 0;
    flex: 1 1 auto;
    max-width: 100%;
    font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    font-weight: 300;
    font-size: 18px;
    line-height: 1.03;
    letter-spacing: 0.01em;
    text-transform: uppercase;
    color: #fff;
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
    word-break: break-word;
}

    @media (min-width: 768px) {
        .hero-accolade-row {
            grid-template-columns: minmax(135px, 185px) minmax(0, 1fr);
            column-gap: 1.35rem;
        }

        .hero-accolade-text {
            font-size: 23px;
        }

        .hero-accolade-icon {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
        }
    }

@media (min-width: 768px) {
    .hero-accolade-text {
        font-size: 23px;
    }

    .hero-accolade-icon {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
    }
}

@media (min-width: 1024px) {
    .hero-accolade-text {
        font-size: 21px;
    }

    .hero-accolade-icon {
        width: 36px;
        height: 36px;
    }
}

@media (min-width: 1280px) {
    .hero-accolade-text {
        font-size: 23px;
    }

    .hero-accolade-icon {
        width: 38px;
        height: 38px;
    }
}

    .hero-main-player-wrap {
        position: absolute;
        left: -4% !important;
        bottom: 0 !important;
        width: 78% !important;
        max-width: 78% !important;
        z-index: 2 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: flex-start !important;
        pointer-events: none;
    }

    .hero-main-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 94vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom left !important;
        display: block !important;
    }

    .hero-action-player-wrap {
        position: absolute;
        left: 48% !important;
        bottom: 5% !important;
        width: 44% !important;
        max-width: 44% !important;
        z-index: 3 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: flex-start !important;
        pointer-events: none;
    }

    .hero-action-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 64vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom center !important;
        display: block !important;
    }

    .hero-national-player-wrap {
        position: absolute;
        left: 36% !important;
        bottom: 16% !important;
        width: 34% !important;
        max-width: 34% !important;
        z-index: 1 !important;
        display: flex !important;
        align-items: flex-end !important;
        justify-content: center !important;
        pointer-events: none;
    }

    .hero-national-player-img {
        width: 100% !important;
        max-width: 100% !important;
        max-height: 54vh !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: bottom center !important;
        display: block !important;
        opacity: 0.80 !important;
    }

    .hero-team-bottom {
        position: absolute;
        right: 0 !important;
        bottom: -2px !important;
        z-index: 4 !important;
        width: 62% !important;
        max-width: 62% !important;
        pointer-events: none;
    }

    .hero-team-bottom img {
        display: block !important;
        width: 100% !important;
        height: auto !important;
        object-fit: contain !important;
    }

    @media (min-width: 768px) {
        .hero-player-card {
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 790px;
            padding-top: 1.25rem;
        }

        .hero-card-stack {
            width: 200px;
            max-width: 200px;
            flex-basis: 200px;
        }

        .hero-position-line {
            font-size: 24px;
        }

        .hero-stats-block {
            max-width: 640px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(135px, 185px) minmax(0, 1fr);
            column-gap: 1.35rem;
            row-gap: 0.55rem;
        }

        .hero-accolade-text {
            font-size: 23px;
        }

        .hero-accolade-icon {
            width: 30px;
            height: 30px;
            flex-basis: 30px;
        }

        .hero-main-player-wrap {
            left: -3% !important;
            bottom: 0 !important;
            width: 80% !important;
            max-width: 80% !important;
        }

        .hero-main-player-img {
            max-height: 95vh !important;
        }

        .hero-action-player-wrap {
            left: 49% !important;
            bottom: 5% !important;
            width: 45% !important;
            max-width: 45% !important;
        }

        .hero-action-player-img {
            max-height: 66vh !important;
        }

        .hero-national-player-wrap {
            left: 30% !important;
            bottom: 40% !important;
            width: 35% !important;
            max-width: 35% !important;
        }

        .hero-national-player-img {
            max-height: 56vh !important;
        }
    }

    @media (min-width: 1024px) {
        .hero-player-card {
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 800px;
            padding-top: 0.75rem;
        }

        .hero-name-and-card {
            gap: 0.75rem;
        }

        .hero-card-stack {
            width: 200px;
            max-width: 200px;
            flex-basis: 200px;
        }

        .hero-position-line {
            font-size: 25px;
        }

        .hero-stats-block {
            max-width: 650px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(145px, 195px) minmax(0, 1fr);
            column-gap: 1.4rem;
            row-gap: 0.55rem;
        }

        .hero-accolade-text {
            font-size: 21px;
        }

        .hero-main-player-wrap {
            left: -2% !important;
            bottom: 0 !important;
            width: 82% !important;
            max-width: 82% !important;
        }

        .hero-main-player-img {
            max-height: 96vh !important;
        }

        .hero-action-player-wrap {
            left: 50% !important;
            bottom: 5% !important;
            width: 46% !important;
            max-width: 46% !important;
        }

        .hero-action-player-img {
            max-height: 68vh !important;
        }

        .hero-national-player-wrap {
            left: 30% !important;
            bottom: 40% !important;
            width: 36% !important;
            max-width: 36% !important;
        }

        .hero-national-player-img {
            max-height: 58vh !important;
        }

        .hero-team-bottom {
            width: 68% !important;
            max-width: 68% !important;
        }
    }

    @media (min-width: 1280px) {
        .hero-player-card {
            padding: 20px 20px 0 0;
            width: 200px !important;
            max-width: 200px !important;
            flex-basis: 200px !important;
        }

        .hero-right-content {
            max-width: 820px;
        }

        .hero-card-stack {
            width: 220px;
            max-width: 220px;
            flex-basis: 220px;
        }

        .hero-position-line {
            font-size: 26px;
        }

        .hero-stats-block {
            margin-top: -40px;
            max-width: 670px;
        }

        .hero-accolade-text {
            font-size: 23px;
        }

        .hero-main-player-wrap {
            left: -1% !important;
            bottom: 0 !important;
            width: 84% !important;
            max-width: 84% !important;
        }

        .hero-main-player-img {
            max-height: 98vh !important;
        }

        .hero-action-player-wrap {
            left: 40% !important;
            bottom: -10% !important;
            width: 47% !important;
            max-width: 47% !important;
        }

        .hero-action-player-img {
            max-height: 70vh !important;
        }

        .hero-national-player-wrap {
            left: 40% !important;
            bottom: 40% !important;
            width: 37% !important;
            max-width: 37% !important;
        }

        .hero-national-player-img {
            max-height: 60vh !important;
        }
    }

    @media (max-width: 1023px) {
        .hero-desktop {
            display: none;
        }

        .hero-mobile-fallback {
            display: block;
            width: 100vw;
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
            overflow: hidden;
            background: {{ $primary }};
            --mobile-design-width: 339;
            --mobile-design-height: 608;
        }

        .mobile-hero-override {
            display: block;
            width: 100vw;
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
            overflow: hidden;
            background: {{ $primary }};
        }

        .mobile-hero-override-image {
            display: block;
            width: 100%;
            height: auto;
            object-fit: cover;
        }

        .mobile-hero-scale-frame {
            position: relative;
            width: 100%;
            max-width: none;
            margin: 0;
            overflow: hidden;
            background: {{ $primary }};
        }

        .mobile-hero-scale-inner {
            position: relative;
            width: 100%;
            aspect-ratio: var(--mobile-design-width) / var(--mobile-design-height);
            background: {{ $primary }};
            overflow: hidden;
        }

        .mobile-hero-shell {
            position: absolute;
            inset: 0;
            width: calc(var(--mobile-design-width) * 1px);
            height: calc(var(--mobile-design-height) * 1px);
            transform-origin: top left;
            transform: scale(calc(100vw / (var(--mobile-design-width) * 1px)));
            background: {{ $primary }};
            color: #fff;
            overflow: hidden;
        }

        .mobile-hero-bg-number {
            position: absolute;
            left: 14px;
            bottom: 350px;
            z-index: 1;
            letter-spacing: -20px;
            font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 220px;
            line-height: 0.8;
            color: rgba(0, 0, 0, 0.08);
            pointer-events: none;
        }

        .mobile-hero-top {
            position: relative;
            z-index: 4;
            padding: 14px 14px 0;
        }

        .mobile-hero-logo-row {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            min-height: 26px;
        }

        .mobile-hero-logo-row img {
            max-height: 50px;
            width: auto;
            object-fit: contain;
        }

        .mobile-hero-head {
            position: relative;
            margin-top: 22px;
            min-height: 388px;
        }

        .mobile-hero-name-wrap {
            position: relative;
            z-index: 1;
            width: 64%;
        }

        .mobile-hero-name-box {
            width: 100%;
            border-radius: 14px;
            padding: 10px 12px 10px;
            z-index: 1;
            position: relative;
            top: -50px;
        }

        .mobile-hero-name-top {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 30px;
            line-height: 0.95;
            font-weight: 400;
            text-transform: uppercase;
            color: rgba(255,255,255,.97);
        }

        .mobile-hero-name-last {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 35px;
            line-height: 0.84;
            font-weight: 900;
            text-transform: uppercase;
            color: #fff;
            letter-spacing: 0.01em;
            padding-top: 3px;
        }

        .mobile-hero-position {
            margin-top: 1px;
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 0.95;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            padding-top: 5px;
        }

        .mobile-signature {
            display: none;
            position: relative;
            z-index: 2;
            margin-top: 16px;
            margin-left: 8px;
            font-size: 48px;
            line-height: 1;
            color: rgba(255,255,255,0.16);
            font-family: cursive;
            transform: rotate(-8deg);
            pointer-events: none;
            user-select: none;
        }

        .mobile-player-stage {
            position: absolute;
            left: 57%;
            transform: translateX(-50%);
            bottom: -20px;
            width: 92%;
            height: 350px;
            z-index: 4;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            overflow: visible;
            pointer-events: none;
        }

        .mobile-player-main {
            width: 120%;
            margin-left: 50px;
            max-width: 500px;
            height: auto;
            display: block;
            object-fit: contain;
            object-position: bottom center;
            filter: drop-shadow(0 14px 24px rgba(0,0,0,.20));
        }

        .mobile-info-grid {
            position: absolute;
            left: 10px;
            right: 10px;
            bottom: 72px;
            z-index: 6;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            align-items: stretch;
        }

        .mobile-stat-card {
            min-height: 218px;
            background: #f3f3f3;
            color: #111;
            border-radius: 10px;
            padding: 10px 10px 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,.08);
            overflow: hidden;
        }

        .mobile-stat-card--left,
        .mobile-stat-card--right {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .mobile-big-value-row {
            display: flex;
            align-items: flex-end;
            gap: 4px;
            margin-bottom: 8px;
            line-height: 0.8;
            flex-wrap: nowrap;
        }

        .mobile-big-value {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 58px;
            line-height: 0.8;
            font-weight: 700;
            letter-spacing: -0.04em;
            color: #000;
        }

        .mobile-big-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 0.9;
            font-weight: 700;
            text-transform: uppercase;
            color: #000;
            padding-bottom: 7px;
        }

        .mobile-small-block {
            padding-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3px;
            margin-bottom: 10px;
        }

        .mobile-small-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            flex: 0 0 50px;
            color: {{ $primary }};
        }

        .mobile-small-logo--empty {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
        }

        .mobile-small-copy {
            min-width: 0;
        }

        .mobile-small-copy-top {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 0.96;
            font-weight: 900;
            text-transform: uppercase;
            color: #111;
        }

        .mobile-small-copy-bottom {
            margin-top: 2px;
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.08;
            font-weight: 400;
            text-transform: uppercase;
            color: rgba(17,17,17,.78);
        }

        .mobile-facts {
            margin-top: auto;
            display: grid;
            gap: 5px;
        }

        .mobile-fact-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            align-items: start;
        }

        .mobile-fact-label,
        .mobile-fact-value {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1;
            text-transform: uppercase;
            color: #111;
        }

        .mobile-fact-label {
            font-weight: 400;
        }

        .mobile-fact-value {
            font-weight: 700;
            text-align: right;
        }

        .mobile-class-row {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            flex-wrap: nowrap;
            margin-bottom: 10px;
        }

        .mobile-class-year {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 56px;
            line-height: 0.8;
            font-weight: 700;
            letter-spacing: -0.04em;
            color: #000;
        }

        .mobile-class-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 0.9;
            font-weight: 700;
            text-transform: uppercase;
            color: #000;
            padding-bottom: 7px;
        }

        .mobile-right-meta {
            display: grid;
            gap: 8px;
            padding-top: 10px;
        }

        .mobile-meta-row {
            display: grid;
            grid-template-columns: 54px 1fr;
            gap: 8px;
            align-items: center;
        }

        .mobile-meta-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1;
            font-weight: 400;
            text-transform: uppercase;
            color: #111;
        }

        .mobile-meta-value {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.05;
            font-weight: 700;
            text-transform: uppercase;
            color: #111;
            text-align: right;
        }

        .mobile-meta-value img {
            max-height: 100px;
            object-fit: contain;
            display: block;
            margin-left: auto;
        }

        .mobile-accolade {
            margin-top: auto;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 8px;
            padding-top: 10px;
        }

        .mobile-accolade-icon {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            opacity: 0.7;
            object-fit: contain;
        }

        .mobile-accolade-text {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.1;
            font-weight: 700;
            text-transform: uppercase;
            text-align: right;
            color: #111;
            flex: 1;
        }

        .mobile-small-block:empty,
        .mobile-facts:empty,
        .mobile-right-meta:empty,
        .mobile-accolade:empty {
            display: none;
        }
    }

    @media (max-width: 1535px), (max-width: 1280px), (max-width: 1150px) {
        .hero-scale {
            transform: scale(1) !important;
            transform-origin: center center;
        }
    }
</style>

<section
    class="hero-desktop relative z-0 overflow-hidden h-[95vh] min-h-[700px] max-h-[940px]"
    style="background-color: {{ $primary }};"
>
    @if ($backgroundImageUrl)
        <div class="absolute inset-0 z-0">
            <img
                src="{{ $backgroundImageUrl }}"
                alt="Hero background"
                class="h-full w-full object-cover"
            />
        </div>
    @endif

    <div
        class="absolute inset-0 z-[1]"
        style="background:
            linear-gradient(90deg, rgba(0,0,0,0.12) 0%, rgba(0,0,0,0.06) 30%, rgba(0,0,0,0.24) 60%, rgba(0,0,0,0.46) 100%),
            radial-gradient(circle at 16% 30%, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.0) 32%);"
    ></div>

    <div
        class="absolute inset-x-0 bottom-0 z-[3] h-[58%] pointer-events-none"
        style="background: linear-gradient(to top, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.82) 22%, rgba(0,0,0,0.48) 48%, rgba(0,0,0,0.18) 68%, rgba(0,0,0,0) 100%);"
    ></div>

    <div class="hero-scale relative z-10 mx-auto h-full max-w-[1800px] px-4 md:px-8 lg:px-10">
        <div class="grid h-full grid-cols-1 lg:grid-cols-[46%_54%]">
            <div class="relative flex min-h-0 items-end">
                @if ($ballLogoUrl)
                    <div class="absolute left-2 top-5 z-30 md:left-3 md:top-6">
                        <img
                            src="{{ $ballLogoUrl }}"
                            alt="Ball logo"
                            class="h-auto w-[78px] md:w-[96px] lg:w-[118px] object-contain drop-shadow-[0_10px_24px_rgba(0,0,0,.35)]"
                        />
                    </div>
                @endif

                <div class="hero-layered-stack absolute inset-0 z-10">
                    @if ($playerNationalImageUrl)
                        <div class="hero-national-player-wrap">
                            <img
                                src="{{ $playerNationalImageUrl }}"
                                alt="{{ $playerFullName }} national team"
                                class="hero-national-player-img drop-shadow-[0_16px_30px_rgba(0,0,0,.28)]"
                            />
                        </div>
                    @endif

                    @if ($playerActionImageUrl)
                        <div class="hero-action-player-wrap">
                            <img
                                src="{{ $playerActionImageUrl }}"
                                alt="{{ $playerFullName }} action"
                                class="hero-action-player-img drop-shadow-[0_16px_30px_rgba(0,0,0,.30)]"
                            />
                        </div>
                    @endif

                    @if ($playerImageUrl)
                        <div class="hero-main-player-wrap">
                            <img
                                src="{{ $playerImageUrl }}"
                                alt="{{ $playerFullName }}"
                                class="hero-main-player-img drop-shadow-[0_18px_35px_rgba(0,0,0,.45)]"
                            />
                        </div>
                    @endif
                </div>
            </div>

            <div class="relative flex items-end justify-end md:pt-2 lg:pt-2">
                @if ($bgJerseyNumber)
                    <div class="pointer-events-none absolute left-[-90px] top-[5%] z-[1] font-iceberg text-[320px] leading-none tracking-[-0.02em] text-white/[0.05] md:text-[420px] lg:text-[520px] xl:text-[640px]">
                        {{ $bgJerseyNumber }}
                    </div>
                @endif

                <div class="hero-right-content relative z-10 h-full">
                    <div class="hero-name-and-card">
                        <div class="hero-name-block">
                            <div class="font-antonio font-light text-[60px] leading-none text-white md:text-[60px] lg:text-[60px]">
                                {{ $firstName }}
                            </div>

                            <div class="font-antonio font-bold text-[80px] leading-none text-white md:text-[80px] lg:text-[90px]">
                                {{ $lastName }}
                            </div>

                            @if (filled($positionDisplay))
                                <div class="hero-position-line">
                                    {{ $positionDisplay }}
                                </div>
                            @endif
                        </div>

                        <div class="hero-card-stack">
                            <div class="hero-card-stack-inner">
                                @if ($plyrCardImageUrl)
                                    <div class="flex justify-end">
                                        <img
                                            src="{{ $plyrCardImageUrl }}"
                                            alt="PlyrCard"
                                            class="hero-player-card object-contain drop-shadow-[0_10px_24px_rgba(0,0,0,.35)]"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="hero-stats-block">
                        <div class="hero-stats-grid">
                            @foreach ($stats as $label => $value)
                                @if (filled($value))
                                    <div class="font-antonio font-light text-[18px] uppercase leading-[1.03] tracking-[0.01em] text-white/95 md:text-[23px] lg:text-[21px] 2xl:text-[23px]">
                                        {{ $label }}
                                    </div>

                                    <div class="font-antonio font-light text-[18px] uppercase leading-[1.03] tracking-[0.01em] text-white md:text-[23px] lg:text-[21px] 2xl:text-[23px]">
                                        {{ $value }}
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if ($desktopAccolades->isNotEmpty())
                            <div class="hero-accolades-list">
                                @foreach ($desktopAccolades as $accolade)
                                    <div class="hero-accolade-row">
                                        <div class="hero-accolade-icon-wrap">
                                            @if (filled($accolade['icon'] ?? ''))
                                                <img
                                                    src="{{ $accolade['icon'] }}"
                                                    alt="Accolade logo"
                                                    class="hero-accolade-icon"
                                                >
                                            @else
                                                <svg
                                                    class="hero-accolade-icon hero-accolade-icon--trophy"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.9"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                                    <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                                    <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                                    <path d="M12 9v7"/>
                                                    <path d="M8 21h8"/>
                                                    <path d="M9.5 16h5"/>
                                                </svg>
                                            @endif
                                        </div>

                                        <div class="hero-accolade-text">
                                            {{ $accolade['text'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($bottomTeamImageUrl)
                        <div class="hero-team-bottom">
                            <img
                                src="{{ $bottomTeamImageUrl }}"
                                alt="Team image"
                                class="align-bottom"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-mobile-fallback">
    @if ($hasMobileHeroOverride)
        <div class="mobile-hero-override">
            <img
                src="{{ $mobileHeroImageUrl }}"
                alt="{{ $playerFullName ?: 'Mobile hero' }}"
                class="mobile-hero-override-image"
            >
        </div>
    @else
        <div class="mobile-hero-scale-frame">
            <div class="mobile-hero-scale-inner">
                <div class="mobile-hero-shell">
                    @if (filled($bgJerseyNumber))
                        <div class="mobile-hero-bg-number">{{ $bgJerseyNumber }}</div>
                    @endif

                    <div class="mobile-hero-top">
                        <div class="mobile-hero-logo-row">
                            @if ($mobileLeagueLogoUrl)
                                <img src="{{ $mobileLeagueLogoUrl }}" alt="League logo">
                            @elseif ($ballLogoUrl)
                                <img src="{{ $ballLogoUrl }}" alt="Sport logo">
                            @elseif ($mobileClubLogoUrl)
                                <img src="{{ $mobileClubLogoUrl }}" alt="Club logo">
                            @endif
                        </div>

                        <div class="mobile-hero-head">
                            <div class="mobile-hero-name-wrap">
                                <div class="mobile-hero-name-box">
                                    <div class="mobile-hero-name-top">
                                        {{ filled($mobileNameLine) ? $mobileNameLine : 'PLAYER NAME #00' }}
                                    </div>

                                    <div class="mobile-hero-name-last">
                                        {{ filled($lastName) ? $lastName : 'LASTNAME' }}
                                    </div>

                                    <div class="mobile-hero-position">
                                        {{ filled($positionDisplay) ? $positionDisplay : 'POSITION' }}
                                    </div>
                                </div>

                                <div class="mobile-signature">
                                    {{ filled($firstName) ? $firstName : 'Name' }}
                                </div>
                            </div>

                            <div class="mobile-player-stage">
                                @if ($playerImageUrl)
                                    <img
                                        src="{{ $playerImageUrl }}"
                                        alt="{{ $playerFullName }}"
                                        class="mobile-player-main"
                                    >
                                @elseif ($mobileMainImage)
                                    <img
                                        src="{{ $mobileMainImage }}"
                                        alt="{{ $playerFullName }}"
                                        class="mobile-player-main"
                                    >
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mobile-info-grid">
                        <div class="mobile-stat-card mobile-stat-card--left">
                            <div class="mobile-big-value-row">
                                <div class="mobile-big-value">
                                    {{ filled($mobileGpa) ? $mobileGpa : '0.0' }}
                                </div>
                                <div class="mobile-big-label">/GPA</div>
                            </div>

                            <div class="mobile-small-block">
                                @if (filled($leftMobileAccoladeImageUrl))
                                    <img src="{{ $leftMobileAccoladeImageUrl }}" alt="Accolade" class="mobile-small-logo">
                                @else
                                    <svg
                                        class="mobile-small-logo"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                        <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                        <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                        <path d="M12 9v7"/>
                                        <path d="M8 21h8"/>
                                        <path d="M9.5 16h5"/>
                                    </svg>
                                @endif

                                <div class="mobile-small-copy">
                                    <div class="mobile-small-copy-top">
                                        {{ filled($leftMobileAccoladeTitle) ? $leftMobileAccoladeTitle : (filled($mobileInternational) ? $mobileInternational : 'NATIONAL TEAM / COUNTRY') }}
                                    </div>
                                    <div class="mobile-small-copy-bottom">
                                        {{ filled($leftMobileAccoladeSubtitle) ? $leftMobileAccoladeSubtitle : '' }}
                                    </div>
                                </div>
                            </div>

                            <div class="mobile-facts">
                                <div class="mobile-fact-row">
                                    <div class="mobile-fact-label">HEIGHT:</div>
                                    <div class="mobile-fact-value">{{ filled($mobileHeight) ? $mobileHeight : '--' }}</div>
                                </div>

                                <div class="mobile-fact-row">
                                    <div class="mobile-fact-label">DOMINANT FOOT:</div>
                                    <div class="mobile-fact-value">{{ filled($mobileDominantFoot) ? $mobileDominantFoot : '--' }}</div>
                                </div>

                                <div class="mobile-fact-row">
                                    <div class="mobile-fact-label">DOB:</div>
                                    <div class="mobile-fact-value">{{ filled($mobileDob) ? $mobileDob : '-- ----' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-stat-card mobile-stat-card--right">
                            <div class="mobile-class-row">
                                <div class="mobile-class-year">
                                    {{ filled($mobileClass) ? $mobileClass : '2026' }}
                                </div>
                                <div class="mobile-class-label">/CLASS</div>
                            </div>

                            <div class="mobile-right-meta">
                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">CLUB:</div>
                                    <div class="mobile-meta-value">
                                        @if (filled($mobileClubLogoUrl))
                                            <img src="{{ $mobileClubLogoUrl }}" alt="Club logo">
                                        @else
                                            {{ filled($mobileClub) ? $mobileClub : 'CLUB' }}
                                        @endif
                                    </div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">LEAGUE:</div>
                                    <div class="mobile-meta-value">
                                        {{ filled($mobileLeague) ? $mobileLeague : 'LEAGUE NAME' }}
                                    </div>
                                </div>
                            </div>

                            @if (filled($rightMobileAccoladeTitle))
                                <div class="mobile-accolade">
                                    @if (filled($rightMobileAccoladeImageUrl))
                                        <img src="{{ $rightMobileAccoladeImageUrl }}" alt="Accolade" class="mobile-accolade-icon">
                                    @else
                                        <svg class="mobile-accolade-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.0">
                                            <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                            <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                            <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                            <path d="M12 9v7"/>
                                            <path d="M8 21h8"/>
                                            <path d="M9.5 16h5"/>
                                        </svg>
                                    @endif

                                    <div class="mobile-accolade-text">
                                        {{ $rightMobileAccoladeTitle }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>