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

    $abbreviatePositionDisplay = function ($value) use ($normalizeDisplayValue) {
        $position = $normalizeDisplayValue($value, ' | ');

        if ($position === '') {
            return '';
        }

        $map = [
            'goalkeeper' => 'GK',
            'keeper' => 'GK',
            'defender' => 'DEF',
            'center_back' => 'CB',
            'centre_back' => 'CB',
            'left_back' => 'LB',
            'right_back' => 'RB',
            'wing_back' => 'WB',
            'midfielder' => 'MID',
            'defensive_midfielder' => 'CDM',
            'central_midfielder' => 'CM',
            'attacking_midfielder' => 'CAM',
            'wide_midfielder' => 'WM',
            'forward' => 'FWD',
            'wide_forward' => 'WF',
            'striker' => 'ST',
            'winger' => 'WG',
            'left_wing' => 'LW',
            'right_wing' => 'RW',
            'point_guard' => 'PG',
            'shooting_guard' => 'SG',
            'small_forward' => 'SF',
            'power_forward' => 'PF',
            'center' => 'C',
            'setter' => 'S',
            'libero' => 'L',
            'outside_hitter' => 'OH',
            'opposite_hitter' => 'OPP',
            'middle_blocker' => 'MB',
            'quarterback' => 'QB',
            'running_back' => 'RB',
            'wide_receiver' => 'WR',
            'tight_end' => 'TE',
        ];

        return collect(explode(' | ', str_replace(' / ', ' | ', $position)))
            ->map(function ($item) use ($map) {
                $key = str($item)->lower()->replace('&', 'and')->replace('-', '_')->replace(' ', '_')->toString();
                return $map[$key] ?? str($item)->replace('_', ' ')->upper()->toString();
            })
            ->implode(' | ');
    };

    $formatDateDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value, ' '));

        if ($date === '') {
            return '';
        }

        try {
            return strtoupper(\Carbon\Carbon::parse($date)->format('M j, Y'));
        } catch (\Throwable $e) {
            return strtoupper($date);
        }
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

    $clubNameRaw = $user?->club?->name ?? $user?->team_name ?? '';
    $leagueNameRaw = $user?->league?->name ?? $user?->club?->league?->name ?? '';
    $nationalTeamNameRaw = $user?->nationalTeam?->name ?? $user?->national_team_name ?? '';

    $clubLogoRaw = $user?->club?->logo ?? '';
    $leagueLogoRaw = $user?->league?->logo ?? $user?->club?->league?->logo ?? '';
    $nationalLogoRaw = $user?->nationalTeam?->logo ?? $user?->national_team_logo ?? '';

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
        $getHeroFieldValue('hero_mobile_image', $user?->mobile_hero_image),
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
        ),
        ' '
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

    $jerseyNumber = trim((string) $getHeroFieldValue('hero_jersey_number', $user?->jersey_number ?? ''));
    $bgJerseyNumber = trim((string) $getHeroFieldValue('hero_bg_jersey_number', $jerseyNumber));

    $positionDisplay = $formatPositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? ''));

    $hometown = $normalizeDisplayValue($getHeroFieldValue('hero_stat_hometown', ''), ' ');
    if ($hometown === '') {
        $hometown = collect([
            $user?->city,
            $user?->state,
        ])->filter(fn ($value) => filled($value))->implode(', ');
    }

    $desktopInternational = $normalizeDisplayValue(
        $getHeroFieldValue(
            'hero_stat_international',
            $user?->natl_team_exp ? $nationalTeamNameRaw : ''
        )
    );

    $stats = [
        'GPA' => $formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? '')),
        'DOB' => filled($getHeroFieldValue('hero_stat_dob', $user?->birth ?? ''))
            ? \Carbon\Carbon::parse($getHeroFieldValue('hero_stat_dob', $user?->birth ?? ''))->format('F Y')
            : '',
        'Hometown' => $hometown,
        'International' => $desktopInternational,
        'League' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_league', $leagueNameRaw)),
        'High School' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? '')),
        'Height' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? '')),
        'Weight' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? '')),
        'Class' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? '')),
        'Coach' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
        'Championship' => $normalizeDisplayValue($getHeroFieldValue('hero_stat_championship', '')),
    ];

    $desktopClubLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_club_logo', $clubLogoRaw),
        ''
    );

    $desktopLeagueLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_league_logo', $leagueLogoRaw),
        ''
    );

    $desktopNationalLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue('hero_national_logo', $nationalLogoRaw),
        ''
    );

    $desktopMaxSpeed = strtoupper($normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_max_speed', $user?->max_speed ?? ''),
        ' '
    ));

    $desktopDominantFoot = strtoupper($normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_dominant_foot', $user?->dominant_foot ?? ''),
        ' '
    ));

    $hasDesktopCardLogos = filled($desktopClubLogoUrl) || filled($desktopLeagueLogoUrl) || filled($desktopNationalLogoUrl);
    $hasDesktopCardStats = filled($desktopMaxSpeed) || filled($desktopDominantFoot);

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

    $desktopNationalTeamName = trim((string) $nationalTeamNameRaw);

    if ($user?->natl_team_exp && $desktopNationalTeamName !== '') {
        $desktopAccolades->push([
            'text' => $desktopNationalTeamName,
            'icon' => filled($desktopNationalLogoUrl) ? $desktopNationalLogoUrl : null,
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

    $mobileClass = strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? ''), ' '));
    $mobileGpa = strtoupper($formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? '')));
    $mobileDob = $formatDateDisplay($getHeroFieldValue('hero_stat_dob', $user?->birth ?? ''));
    $mobileHeight = strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? ''), ' '));
    $mobileWeight = strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? ''), ' '));
    $mobileMaxSpeed = strtoupper($normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_max_speed', $user?->max_speed ?? ''),
        ' '
    ));
    $mobileCoach = strtoupper(trim($normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? ''),
        ' '
    )));

    $mobileInternational = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_international', $nationalTeamNameRaw)
    );

    $mobileClub = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_club', $clubNameRaw)
    );

    $mobileLeague = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_league', $leagueNameRaw)
    );

    $mobileDominantFoot = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_dominant_foot', $user?->dominant_foot ?? '')
    );

    $mobileClubLogoUrl = $desktopClubLogoUrl;
    $mobileLeagueLogoUrl = $desktopLeagueLogoUrl;
    $mobileNationalLogoUrl = $desktopNationalLogoUrl;

    $mobileTopLogoUrl = $mobileLeagueLogoUrl ?: $ballLogoUrl ?: $mobileClubLogoUrl ?: '';

    $mobileMainImage = $playerImageUrl ?: $backgroundImageUrl;
    $hasMobileHeroOverride = filled($mobileHeroImageUrl);

    $displayPositionMobile = $abbreviatePositionDisplay($getHeroFieldValue('hero_display_position', $user?->position ?? ''));

    $firstNameLength = mb_strlen(trim($firstName));
    $lastNameLength = mb_strlen(trim($lastName));

    $mobileFirstNameClass = '';
    $mobileLastNameClass = '';

    if ($firstNameLength > $lastNameLength) {
        $mobileLastNameClass = ' mobile-hero-name-last--bigger';
        $mobileFirstNameClass = ' mobile-hero-name-top--smaller';
    } elseif ($lastNameLength > $firstNameLength) {
        $mobileFirstNameClass = ' mobile-hero-name-top--bigger';
        $mobileLastNameClass = ' mobile-hero-name-last--smaller';
    }

    $mobileJerseyDisplay = filled($jerseyNumber)
        ? '#' . ltrim($jerseyNumber, '#')
        : (filled($bgJerseyNumber) ? '#' . ltrim($bgJerseyNumber, '#') : '');
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Luxurious+Script&display=swap');

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
        width: 250px !important;
        max-width: 250px !important;
        height: auto !important;
        flex: 0 0 250px !important;
    }

    .hero-right-content {
        width: 100%;
        max-width: 760px;
        margin-left: auto;
        padding-top: 1.5rem;
        --card-stack-width: 250px;
        --card-stack-offset: 30px;
        --card-under-width: 188px;
    }

    .hero-name-and-card {
        position: relative;
        display: block;
        padding-right: calc(var(--card-stack-width) + var(--card-stack-offset));
        min-height: 0;
    }

    .hero-name-block {
        margin: 0 !important;
        padding: 0 !important;
        min-width: 0;
        max-width: calc(100% - var(--card-stack-width) - var(--card-stack-offset));
    }

    .hero-name-top-line {
        display: flex;
        align-items: flex-end;
        gap: 14px;
        flex-wrap: nowrap;
        min-width: 0;
    }

    .hero-first-name-inline {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 300;
        font-size: 60px;
        line-height: 0.9;
        color: #fff;
        flex: 0 0 auto;
    }

    .hero-position-inline {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 300;
        font-size: 18px;
        line-height: 1;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.92);
        white-space: nowrap;
        flex: 0 1 auto;
        padding-bottom: 8px;
    }

    .hero-last-name-inline {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 700;
        font-size: 80px;
        line-height: 0.95;
        color: #fff;
        margin-top: 2px;
    }

    .hero-card-stack {
        position: absolute;
        top: 0;
        right: 0;
        width: var(--card-stack-width);
        max-width: var(--card-stack-width);
        flex: 0 0 var(--card-stack-width);
        display: block;
        z-index: 3;
    }

    .hero-card-stack-inner {
        position: relative;
        width: 100%;
        display: block;
    }

    .hero-card-under {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: var(--card-under-width);
        display: grid;
        gap: 12px;
        justify-items: start;
    }

    .hero-card-logo-row {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        align-items: center;
        justify-items: center;
        gap: 10px;
        margin-top: 0;
    }

    .hero-card-logo-slot {
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hero-card-logo {
        display: block;
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .hero-card-basic-stats {
        width: 100%;
        display: grid;
        gap: 8px;
        margin-top: 0;
    }

    .hero-card-basic-stat {
        display: grid;
        grid-template-columns: 16px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
    }

    .hero-card-basic-stat-icon {
        width: 14px;
        height: 14px;
        color: rgba(255,255,255,.88);
        display: block;
    }

    .hero-card-basic-stat-copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .hero-card-basic-stat-label {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-size: 11px;
        line-height: 1;
        font-weight: 400;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,.78);
    }

    .hero-card-basic-stat-value {
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-size: 16px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #fff;
    }

    .hero-stats-block {
        margin-top: 18px;
        margin-left: 0 !important;
        padding-left: 0 !important;
        max-width: calc(100% - var(--card-stack-width) - var(--card-stack-offset));
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
        gap: 0.6rem;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .hero-accolade-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .hero-accolade-icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        align-self: center;
        flex: 0 0 auto;
        padding-top: 0;
    }

    .hero-accolade-icon {
        width: 52px;
        height: 52px;
        object-fit: contain;
        display: block;
        flex: 0 0 52px;
    }

    .hero-accolade-icon--trophy {
        width: 24px;
        height: 24px;
        flex-basis: 24px;
        color: {{ $primary }};
    }

    .hero-accolade-text {
        min-width: 0;
        flex: 0 1 100%;
        max-width: min(100%, 440px);
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
        font-weight: 300;
        font-size: 18px;
        line-height: 1.4;
        letter-spacing: 0.01em;
        text-transform: uppercase;
        color: #fff;
        overflow: hidden;
        word-break: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        line-clamp: 2;
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
        .hero-accolade-text {
            font-size: 23px;
        }

        .hero-accolade-icon {
            width: 56px;
            height: 56px;
            flex-basis: 56px;
        }

        .hero-accolade-icon--trophy {
            width: 26px;
            height: 26px;
            flex-basis: 26px;
        }

        .hero-player-card {
            width: 250px !important;
            max-width: 250px !important;
            flex-basis: 250px !important;
        }

        .hero-right-content {
            max-width: 790px;
            padding-top: 1.25rem;
            --card-stack-width: 250px;
            --card-stack-offset: 32px;
            --card-under-width: 196px;
        }

        .hero-card-stack {
            width: var(--card-stack-width);
            max-width: var(--card-stack-width);
            flex-basis: var(--card-stack-width);
        }

        .hero-card-logo-slot {
            width: 54px;
            height: 54px;
        }

        .hero-first-name-inline {
            font-size: 60px;
        }

        .hero-position-inline {
            font-size: 19px;
            padding-bottom: 8px;
        }

        .hero-last-name-inline {
            font-size: 80px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(135px, 185px) minmax(0, 1fr);
            column-gap: 1.35rem;
            row-gap: 0.55rem;
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
    }

    @media (min-width: 1024px) {
        .hero-accolade-text {
            font-size: 21px;
        }

        .hero-accolade-icon {
            width: 58px;
            height: 58px;
            flex-basis: 58px;
        }

        .hero-accolade-icon--trophy {
            width: 28px;
            height: 28px;
            flex-basis: 28px;
        }

        .hero-player-card {
            width: 250px !important;
            max-width: 250px !important;
            flex-basis: 250px !important;
        }

        .hero-right-content {
            max-width: 800px;
            padding-top: 0.75rem;
            --card-stack-width: 250px;
            --card-stack-offset: 34px;
            --card-under-width: 198px;
        }

        .hero-card-stack {
            width: var(--card-stack-width);
            max-width: var(--card-stack-width);
            flex-basis: var(--card-stack-width);
        }

        .hero-first-name-inline {
            font-size: 60px;
        }

        .hero-position-inline {
            font-size: 20px;
        }

        .hero-last-name-inline {
            font-size: 90px;
        }

        .hero-stats-grid {
            grid-template-columns: minmax(145px, 195px) minmax(0, 1fr);
            column-gap: 1.4rem;
            row-gap: 0.55rem;
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
    }

    @media (min-width: 1280px) {
        .hero-accolade-text {
            font-size: 23px;
        }

        .hero-accolade-icon {
            width: 80px;
            height: 80px;
            flex-basis: 80px;
        }

        .hero-accolade-icon--trophy {
            width: 32px;
            height: 32px;
            flex-basis: 32px;
        }

        .hero-player-card {
            padding: 20px 20px 0 0;
            width: 270px !important;
            max-width: 270px !important;
            flex-basis: 270px !important;
        }

        .hero-right-content {
            max-width: 820px;
            --card-stack-width: 270px;
            --card-stack-offset: 36px;
            --card-under-width: 208px;
        }

        .hero-card-stack {
            width: var(--card-stack-width);
            max-width: var(--card-stack-width);
            flex-basis: var(--card-stack-width);
        }

        .hero-card-logo-slot {
            width: 58px;
            height: 58px;
        }

        .hero-card-basic-stat-label {
            font-size: 12px;
        }

        .hero-card-basic-stat-value {
            font-size: 18px;
        }

        .hero-first-name-inline {
            font-size: 60px;
        }

        .hero-position-inline {
            font-size: 21px;
            padding-bottom: 10px;
        }

        .hero-last-name-inline {
            font-size: 90px;
        }

        .hero-stats-block {
            margin-top: 18px;
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
            --mobile-design-width: 390;
            --mobile-design-height: 680;
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
            left: 4px;
            top: 170px;
            z-index: 1;
            letter-spacing: -18px;
            font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 250px;
            line-height: 0.8;
            color: rgba(255,255,255,0.10);
            pointer-events: none;
        }

        .mobile-hero-top {
            position: relative;
            z-index: 4;
            padding: 12px 16px 0;
        }

        .mobile-hero-logo-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 40px;
        }

        .mobile-hero-logo-row img {
            max-height: 38px;
            width: auto;
            object-fit: contain;
        }

        .mobile-hero-head {
            position: relative;
            margin-top: 18px;
            min-height: 430px;
        }

        .mobile-hero-name-wrap {
            position: relative;
            z-index: 5;
            width: 58%;
        }

        .mobile-hero-name-box {
            margin-top: -30px;
            width: 100%;
            position: relative;
            z-index: 5;
        }

        .mobile-hero-jersey {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 38px;
            line-height: 0.9;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            letter-spacing: -0.04em;
            margin-bottom: 2px;
        }

        .mobile-hero-name-top {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 45px;
            line-height: 1.0;
            font-weight: 800;
            text-transform: uppercase;
            color: #fff;
            letter-spacing: -0.05em;
        }

        .mobile-hero-name-last {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 56px;
            line-height: 0.86;
            font-weight: 800;
            text-transform: uppercase;
            color: #fff;
            letter-spacing: -0.05em;
            margin-top: 2px;
        }

        .mobile-hero-name-top--bigger {
            font-size: 50px;
        }

        .mobile-hero-name-top--smaller {
            font-size: 50px;
        }

        .mobile-hero-name-last--bigger {
            font-size: 60px;
        }

        .mobile-hero-name-last--smaller {
            font-size: 52px;
        }

        .mobile-hero-position {
            margin-top: 12px;
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 22px;
            line-height: 0.95;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
        }

        .mobile-signature {
            position: absolute;
            left: 8px;
            top: 180px;
            z-index: 1;
            font-size: 110px;
            line-height: 1;
            color: rgba(255,255,255,0.14);
            font-family: "Luxurious Script", cursive;
            transform: rotate(-7deg);
            pointer-events: none;
            user-select: none;
        }

        .mobile-player-stage {
            position: absolute;
            right: -10px;
            bottom: 0;
            width: 63%;
            height: 440px;
            z-index: 10;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            overflow: visible;
            pointer-events: none;
        }

        .mobile-player-main {
            width: auto;
            height: 100%;
            max-width: none;
            display: block;
            object-fit: contain;
            object-position: bottom center;
            filter: drop-shadow(0 14px 24px rgba(0,0,0,.20));
        }

        .mobile-info-grid {
            position: absolute;
            left: 6px;
            right: 6px;
            bottom: 70px;
            z-index: 8;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            align-items: stretch;
        }

        .mobile-stat-card {
            min-height: 240px;
            background: #f1f1f1;
            color: #111;
            border-radius: 8px;
            padding: 10px 10px 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,.08);
            overflow: hidden;
            position: relative;
        }

        .mobile-stat-card:before,
        .mobile-stat-card:after {
            display: none;
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
            gap: 6px;
            margin-bottom: 12px;
            line-height: 0.8;
            flex-wrap: nowrap;
        }

        .mobile-big-value {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 76px;
            line-height: 0.8;
            font-weight: 900;
            letter-spacing: -0.05em;
            color: #000;
        }

        .mobile-big-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 24px;
            line-height: 0.9;
            font-weight: 900;
            text-transform: uppercase;
            color: #000;
            padding-bottom: 9px;
        }

        .mobile-org-list {
            display: grid;
            gap: 12px;
            margin-top: auto;
        }

        .mobile-org-row {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 10px;
            align-items: center;
        }

        .mobile-org-icon {
            width: 42px;
            height: 42px;
            object-fit: contain;
            color: {{ $primary }};
        }

        .mobile-org-copy-title {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 18px;
            line-height: 0.95;
            font-weight: 900;
            text-transform: uppercase;
            color: #111;
        }

        .mobile-org-copy-value {
            margin-top: 2px;
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.05;
            font-weight: 500;
            color: #111;
            text-transform: none;
        }

        .mobile-class-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 4px;
            flex-wrap: nowrap;
            margin-bottom: 14px;
            min-width: 0;
        }

        .mobile-class-year {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 64px;
            line-height: 0.8;
            font-weight: 900;
            letter-spacing: -0.045em;
            color: #000;
            flex: 0 1 auto;
            min-width: 0;
        }

        .mobile-class-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 24px;
            line-height: 0.9;
            font-weight: 900;
            text-transform: uppercase;
            color: #000;
            padding-bottom: 8px;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .mobile-right-meta {
            display: grid;
            gap: 10px;
            padding-top: 2px;
        }

        .mobile-meta-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: baseline;
        }

        .mobile-meta-label {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1;
            font-weight: 500;
            text-transform: uppercase;
            color: #111;
        }

        .mobile-meta-value {
            font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1;
            font-weight: 800;
            text-transform: uppercase;
            color: #111;
            text-align: right;
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
                            <div class="hero-name-top-line">
                                <div class="hero-first-name-inline">
                                    {{ $firstName }}
                                </div>

                                @if (filled($positionDisplay))
                                    <div class="hero-position-inline">
                                        {{ $positionDisplay }}
                                    </div>
                                @endif
                            </div>

                            <div class="hero-last-name-inline">
                                {{ $lastName }}
                            </div>
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

                                @if ($hasDesktopCardLogos || $hasDesktopCardStats)
                                    <div class="hero-card-under">
                                        @if ($hasDesktopCardLogos)
                                            <div class="hero-card-logo-row">
                                                @if (filled($desktopClubLogoUrl))
                                                    <div class="hero-card-logo-slot">
                                                        <img
                                                            src="{{ $desktopClubLogoUrl }}"
                                                            alt="Club logo"
                                                            class="hero-card-logo"
                                                        >
                                                    </div>
                                                @endif

                                                @if (filled($desktopLeagueLogoUrl))
                                                    <div class="hero-card-logo-slot">
                                                        <img
                                                            src="{{ $desktopLeagueLogoUrl }}"
                                                            alt="League logo"
                                                            class="hero-card-logo"
                                                        >
                                                    </div>
                                                @endif

                                                @if (filled($desktopNationalLogoUrl))
                                                    <div class="hero-card-logo-slot">
                                                        <img
                                                            src="{{ $desktopNationalLogoUrl }}"
                                                            alt="National team logo"
                                                            class="hero-card-logo"
                                                        >
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($hasDesktopCardStats)
                                            <div class="hero-card-basic-stats">
                                                @if (filled($desktopMaxSpeed))
                                                    <div class="hero-card-basic-stat">
                                                        <svg class="hero-card-basic-stat-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                            <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>
                                                        </svg>

                                                        <div class="hero-card-basic-stat-copy">
                                                            <div class="hero-card-basic-stat-label">Max Speed</div>
                                                            <div class="hero-card-basic-stat-value">{{ $desktopMaxSpeed }}</div>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if (filled($desktopDominantFoot))
                                                    <div class="hero-card-basic-stat">
                                                        <svg class="hero-card-basic-stat-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <path d="M7 6.5c0-1.4 1-2.5 2.2-2.5S11.5 5 11.5 6.2c0 1-.6 1.8-1.4 2.2"/>
                                                            <path d="M12.7 7.1c.8-.4 1.4-1.2 1.4-2.1 0-1.2-1-2.2-2.2-2.2S9.7 3.8 9.7 5"/>
                                                            <path d="M8.4 10.2c-1 .9-1.9 2.4-1.9 4.3 0 3.1 2.2 5.5 5.5 5.5 2.3 0 4.1-1 5.1-2.5"/>
                                                            <path d="M9.6 9.4c1.4-.5 3-.3 4.2.8 1.5 1.4 1.8 3.6.9 5.4"/>
                                                            <path d="M16.8 15.7c.8-.9 1.2-2 1.2-3.3 0-2.8-2.3-5.1-5.1-5.1-.8 0-1.6.2-2.3.5"/>
                                                        </svg>

                                                        <div class="hero-card-basic-stat-copy">
                                                            <div class="hero-card-basic-stat-label">Dominant Foot</div>
                                                            <div class="hero-card-basic-stat-value">{{ $desktopDominantFoot }}</div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
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
                            @if ($mobileTopLogoUrl)
                                <img src="{{ $mobileTopLogoUrl }}" alt="Top logo">
                            @endif
                        </div>

                        <div class="mobile-hero-head">
                            <div class="mobile-hero-name-wrap">
                                <div class="mobile-hero-name-box">
                                    @if (filled($mobileJerseyDisplay))
                                        <div class="mobile-hero-jersey">
                                            {{ $mobileJerseyDisplay }}
                                        </div>
                                    @endif

                                    <div class="mobile-hero-name-top{{ $mobileFirstNameClass }}">
                                        {{ filled($firstName) ? $firstName : 'PLAYER' }}
                                    </div>

                                    <div class="mobile-hero-name-last{{ $mobileLastNameClass }}">
                                        {{ filled($lastName) ? $lastName : 'LASTNAME' }}
                                    </div>

                                    <div class="mobile-hero-position">
                                        {{ filled($displayPositionMobile) ? $displayPositionMobile : 'POSITION' }}
                                    </div>
                                </div>

                                <div class="mobile-signature">
                                    {{ filled($firstName) ? ucfirst(strtolower($firstName)) : 'Name' }}
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

                            <div class="mobile-org-list">
                                <div class="mobile-org-row">
                                    @if (filled($mobileNationalLogoUrl))
                                        <img src="{{ $mobileNationalLogoUrl }}" alt="National logo" class="mobile-org-icon">
                                    @else
                                        <svg class="mobile-org-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                            <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                            <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                            <path d="M12 9v7"/>
                                            <path d="M8 21h8"/>
                                            <path d="M9.5 16h5"/>
                                        </svg>
                                    @endif
                                    <div>
                                        <div class="mobile-org-copy-title">NATIONAL TEAM</div>
                                        <div class="mobile-org-copy-value">
                                            {{ filled($mobileInternational) ? $mobileInternational : 'NATIONAL TEAM / COUNTRY' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mobile-org-row">
                                    @if (filled($mobileClubLogoUrl))
                                        <img src="{{ $mobileClubLogoUrl }}" alt="Club logo" class="mobile-org-icon">
                                    @else
                                        <svg class="mobile-org-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                            <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                            <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                            <path d="M12 9v7"/>
                                            <path d="M8 21h8"/>
                                            <path d="M9.5 16h5"/>
                                        </svg>
                                    @endif
                                    <div>
                                        <div class="mobile-org-copy-title">CLUB</div>
                                        <div class="mobile-org-copy-value">
                                            {{ filled($mobileClub) ? $mobileClub : 'CLUB' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mobile-org-row">
                                    @if (filled($mobileLeagueLogoUrl))
                                        <img src="{{ $mobileLeagueLogoUrl }}" alt="League logo" class="mobile-org-icon">
                                    @else
                                        <svg class="mobile-org-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                            <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                            <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                            <path d="M12 9v7"/>
                                            <path d="M8 21h8"/>
                                            <path d="M9.5 16h5"/>
                                        </svg>
                                    @endif
                                    <div>
                                        <div class="mobile-org-copy-title">LEAGUE</div>
                                        <div class="mobile-org-copy-value">
                                            {{ filled($mobileLeague) ? $mobileLeague : 'LEAGUE NAME' }}
                                        </div>
                                    </div>
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
                                    <div class="mobile-meta-label">HEIGHT:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileHeight) ? $mobileHeight : '--' }}</div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">WEIGHT:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileWeight) ? $mobileWeight : '--' }}</div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">MAX SPEED:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileMaxSpeed) ? $mobileMaxSpeed : '--' }}</div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">DOMINANT FOOT:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileDominantFoot) ? $mobileDominantFoot : '--' }}</div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">DOB:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileDob) ? $mobileDob : '-- ----' }}</div>
                                </div>

                                <div class="mobile-meta-row">
                                    <div class="mobile-meta-label">COACH:</div>
                                    <div class="mobile-meta-value">{{ filled($mobileCoach) ? $mobileCoach : '--' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>