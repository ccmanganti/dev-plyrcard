@php
    $user = $website->user;
    $club = $user?->club;

    /*
    |--------------------------------------------------------------------------
    | Theme Colors
    |--------------------------------------------------------------------------
    | Priority:
    | 1. Hero field value
    | 2. Club model value
    | 3. Website value
    | 4. Hardcoded default
    */
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
        return filled($record?->value) ? $record->value : $default;
    };

    $primary = $getHeroFieldValue(
        'primary_color',
        $club?->primary_color
            ?: $website->primary_color
            ?: '#cf4446'
    );

    $secondary = $getHeroFieldValue(
        'secondary_color',
        $club?->secondary_color
            ?: $website->secondary_color
            ?: '#111111'
    );

    $accent = $getHeroFieldValue(
        'accent_color',
        $club?->accent_color
            ?: $website->accent_color
            ?: '#ffffff'
    );

    $bg = $getHeroFieldValue(
        'background_color',
        $club?->background_color
            ?: $website->background_color
            ?: '#0b0b0b'
    );

    $surface = $getHeroFieldValue(
        'surface_color',
        $club?->surface_color
            ?: $website->surface_color
            ?: '#171717'
    );

    $text1 = $getHeroFieldValue(
        'text_primary_color',
        $club?->text_primary_color
            ?: $website->text_primary_color
            ?: '#ffffff'
    );

    $text2 = $getHeroFieldValue(
        'text_secondary_color',
        $club?->text_secondary_color
            ?: $website->text_secondary_color
            ?: '#ffe5e5'
    );

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

    $normalizeDisplayValue = function ($value, $separator = ' | ') {
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

    $formatCoachDisplay = function ($value) use ($normalizeDisplayValue) {
        $fullName = trim($normalizeDisplayValue($value, ' '));

        if ($fullName === '') {
            return '';
        }

        return strtoupper($fullName);
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
        $gpa = trim($normalizeDisplayValue($value, ' '));

        if ($gpa === '') {
            return '';
        }

        if (is_numeric($gpa)) {
            return number_format((float) $gpa, 1, '.', '');
        }

        return $gpa;
    };

    $playerFullName = trim($getHeroFieldValue('hero_player_name', ($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));
    $statsTitle = strtoupper($getHeroFieldValue('hero_stats_title', 'STATISTICS'));

    $plyrCardImageUrl = $resolveMediaUrl(
        $getHeroFieldValue(
            'hero_plyrcard_image',
            $club?->plyrcard_image
                ?? $website->plyrcard_image
                ?? $user?->plyrcard_image
        ),
        ''
    );

    $playerImageUrl = $resolveMediaUrl(
        $getHeroFieldValue(
            'hero_player_image',
            $club?->player_image
                ?? $website->player_image
                ?? $user?->player_image
        ),
        ''
    );

    $mobileHeroImageUrl = $resolveMediaUrl(
        $getHeroFieldValue(
            'hero_mobile_image',
            $club?->mobile_hero_image
                ?? $website->mobile_hero_image
                ?? $user?->mobile_hero_image
        ),
        ''
    );

    $backgroundImageUrl = $resolveMediaUrl(
        $getHeroFieldValue(
            'hero_background_image',
            $club?->hero_background_image
                ?? $website->hero_background_image
                ?? ''
        ),
        ''
    );

    $ballLogoUrl = $resolveMediaUrl(
        $getHeroFieldValue(
            'hero_ball_logo',
            $club?->ball_logo
                ?? $website->ball_logo
                ?? ''
        ),
        ''
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

    if (! $ballLogoUrl) {
        $ballLogoUrl = $defaultBallLogoUrl;
    }

    $jerseyNumber = trim((string) $getHeroFieldValue('hero_jersey_number', $user?->jersey_number ?? ''));
    $bgJerseyNumber = trim((string) $getHeroFieldValue('hero_bg_jersey_number', $jerseyNumber));

    $hometown = $normalizeDisplayValue($getHeroFieldValue('hero_stat_hometown', ''), ' ');
    if ($hometown === '') {
        $hometown = strtoupper(collect([
            $user?->city,
            $user?->state,
        ])->filter(fn ($value) => filled($value))->implode(', '));
    }

    $clubValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_club', $user?->club?->name ?? $user?->team_name ?? ''),
        ' '
    );

    $leagueValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_league', $user?->club?->league?->name ?? ''),
        ' '
    );

    $highSchoolValue = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_high_school', $user?->school?->name ?? ''),
        ' '
    );

    $stats = [
        'GPA' => strtoupper($formatGpaDisplay($getHeroFieldValue('hero_stat_gpa', $user?->gpa ?? ''))),
        'DOB' => $formatDateDisplay($getHeroFieldValue('hero_stat_dob', $user?->birth ?? '')),
        'HOMETOWN' => $hometown,
        'POSITION' => $abbreviatePositionDisplay($getHeroFieldValue('hero_stat_position', $user?->position ?? '')),
        'CLUB' => strtoupper($clubValue),
        'LEAGUE' => strtoupper($leagueValue),
        'HIGH SCHOOL' => strtoupper($highSchoolValue),
        'HEIGHT' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_height', $user?->height ?? ''), ' ')),
        'WEIGHT' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_weight', $user?->weight ?? ''), ' ')),
        'CLASS' => strtoupper($normalizeDisplayValue($getHeroFieldValue('hero_stat_class', $user?->year ?? ''), ' ')),
        'COACH' => $formatCoachDisplay($getHeroFieldValue('hero_stat_coach', $user?->club_coach ?? '')),
    ];

    $nameParts = preg_split('/\s+/', trim($playerFullName)) ?: [];
    $firstName = strtoupper($nameParts[0] ?? '');
    $lastName = strtoupper(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '');

    $displayPosition = $abbreviatePositionDisplay($getHeroFieldValue('hero_display_position', $user?->position ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Mobile values
    |--------------------------------------------------------------------------
    */
    $mobileClass = $stats['CLASS'] ?? '';
    $mobileGpa = $stats['GPA'] ?? '';
    $mobileDob = $stats['DOB'] ?? '';
    $mobileHeight = $stats['HEIGHT'] ?? '';
    $mobileWeight = $stats['WEIGHT'] ?? '';
    $mobileMaxSpeed = strtoupper($normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_max_speed', $user?->max_speed ?? ''),
        ' '
    ));
    $mobileCoach = $stats['COACH'] ?? '';

    $mobileInternational = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_international', $user?->national_team_name ?? '')
    );

    $mobileClub = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_club', $user?->club?->name ?? '')
    );

    $mobileLeague = $normalizeDisplayValue(
        $getHeroFieldValue('hero_stat_league', $user?->club?->league?->name ?? '')
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

    $mobileOrgRows = [];

    if (filled($mobileInternational)) {
        $mobileOrgRows[] = [
            'title' => 'NATIONAL TEAM',
            'value' => $mobileInternational,
            'logo' => $mobileNationalLogoUrl,
        ];
    }

    if (filled($mobileClub)) {
        $mobileOrgRows[] = [
            'title' => 'CLUB',
            'value' => $mobileClub,
            'logo' => $mobileClubLogoUrl,
        ];
    }

    if (filled($mobileLeague)) {
        $mobileOrgRows[] = [
            'title' => 'LEAGUE',
            'value' => $mobileLeague,
            'logo' => $mobileLeagueLogoUrl,
        ];
    }

    $mobileTopLogoUrl = $mobileLeagueLogoUrl ?: $ballLogoUrl ?: $mobileClubLogoUrl ?: '';

    $mobileMainImage = $playerImageUrl ?: $backgroundImageUrl;
    $hasMobileHeroOverride = filled($mobileHeroImageUrl);

    /*
    |--------------------------------------------------------------------------
    | Dynamic mobile name sizing
    |--------------------------------------------------------------------------
    */
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

    .font-antonio{
        font-family: "Antonio", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .font-iceberg {
        font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif !important;
    }

    .hero-template-basic-desktop{
        display:block;
    }

    .hero-template-basic-mobile{
        display:none;
    }

    .hero-basic-stat-row:nth-child(even){
        background: rgba(255,255,255,0.035);
    }

    .hero-basic-stat-row:nth-child(odd){
        background: transparent;
    }

    @media (max-width: 1023px){
        .hero-template-basic-desktop{
            display:none;
        }

        .hero-template-basic-mobile{
            display:block;
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
            margin-top:-30px;
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
</style>

<section
    class="hero-template-basic-desktop relative overflow-hidden h-screen w-full"
    style="background-color: {{ $primary }};"
>
    @if($backgroundImageUrl)
        <div class="absolute inset-0 z-0">
            <img
                src="{{ $backgroundImageUrl }}"
                alt="Hero background"
                class="h-full w-full object-cover opacity-100"
            >
        </div>
    @endif

    <div class="absolute inset-0 z-[1]" style="background:
        linear-gradient(to bottom, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.01) 35%, rgba(0,0,0,0.04) 100%),
        linear-gradient(to top, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.00) 28%);
    "></div>

    <div
        class="pointer-events-none absolute inset-x-0 bottom-0 z-[6] h-[18%]"
        style="background: linear-gradient(to top, rgba(0,0,0,0.20) 0%, rgba(0,0,0,0.08) 45%, rgba(0,0,0,0.00) 100%);"
    ></div>

    <div class="relative z-10 h-full w-full max-w-[1880px] mx-auto">
        @if($bgJerseyNumber)
            <div class="pointer-events-none absolute left-[11.2%] top-[3.8%] z-[3] font-antonio font-bold leading-none text-white/[0.10] text-[clamp(22rem,27vw,34rem)]">
                {{ $bgJerseyNumber }}
            </div>
        @endif

        <div class="absolute left-[1.2%] top-[15.9%] z-10 w-[24%]">
            @if($jerseyNumber)
                <div class="font-antonio font-bold text-white leading-[0.90] tracking-[-0.05em] text-[clamp(7.4rem,10vw,12.2rem)] mb-1">
                    #{{ ltrim($jerseyNumber, '#') }}
                </div>
            @endif

            <div class="font-antonio font-bold text-white uppercase leading-[0.90] tracking-[-0.065em] text-[clamp(5.8rem,7.8vw,9.2rem)]">
                {{ $firstName }}
            </div>

            @if($lastName)
                <div class="mt-1 font-antonio font-semibold text-white uppercase leading-[0.94] tracking-[-0.055em] text-[clamp(3.7rem,5vw,5.9rem)]">
                    {{ $lastName }}
                </div>
            @endif

            @if($displayPosition)
                <div class="mt-4 font-antonio font-light text-white uppercase leading-none tracking-[-0.02em] text-[clamp(1.7rem,2.3vw,2.8rem)]">
                    {{ $displayPosition }}
                </div>
            @endif
        </div>

        <div class="absolute inset-y-0 left-[25.5%] z-10 w-[27.5%] flex items-end justify-center pointer-events-none">
            @if($playerImageUrl)
                <img
                    src="{{ $playerImageUrl }}"
                    alt="{{ $playerFullName }}"
                    class="block h-[clamp(86%,99%,100%)] w-auto max-w-none object-contain drop-shadow-[0_26px_46px_rgba(0,0,0,0.22)]"
                >
            @endif
        </div>

        <div class="absolute right-[2.2%] top-[2.1%] z-10 w-[40.5%] h-[95.5%] flex flex-col">
            <div class="flex items-start justify-between gap-4">
                <div class="font-antonio font-light text-white uppercase tracking-[-0.04em] leading-[0.88] text-[clamp(4.1rem,5.4vw,6.4rem)]">
                    {{ $statsTitle }}
                </div>

                @if($ballLogoUrl)
                    <div class="pt-2">
                        <img
                            src="{{ $ballLogoUrl }}"
                            alt="Ball logo"
                            class="w-[clamp(5.1rem,6.5vw,8.1rem)] h-auto object-contain"
                        >
                    </div>
                @endif
            </div>

            <div class="mt-[clamp(0.1rem,0.2vw,0.3rem)]">
                @foreach($stats as $label => $value)
                    @if(filled(strip_tags((string) $value)))
                        <div class="hero-basic-stat-row grid grid-cols-[clamp(10.5rem,12vw,13.5rem)_1fr] items-start gap-x-4 px-3 py-[0.4rem]">
                            <div class="font-antonio font-light text-white uppercase leading-[1.02] tracking-[-0.02em] text-[clamp(1.38rem,1.82vw,2.15rem)]">
                                {{ $label }}
                            </div>

                            <div class="font-antonio font-light text-white uppercase leading-[1.02] tracking-[-0.02em] text-[clamp(1.38rem,1.82vw,2.15rem)]">
                                {!! $value !!}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-auto pb-4">
                <div class="font-antonio font-black uppercase leading-none tracking-[-0.08em] text-[clamp(4.8rem,6vw,7rem)]">
                    <span style="color:#111111;">PLYR</span><span style="color:#ffffff;">CARD</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-template-basic-mobile">
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
                                        {{ filled($displayPosition) ? $displayPosition : 'POSITION' }}
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
                                @foreach ($mobileOrgRows as $row)
                                    <div class="mobile-org-row">
                                        @if (filled($row['logo']))
                                            <img src="{{ $row['logo'] }}" alt="{{ $row['title'] }} logo" class="mobile-org-icon">
                                        @else
                                            <svg class="mobile-org-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M8 3h8v3a4 4 0 0 1-8 0V3Z"/>
                                                <path d="M6 5H4a3 3 0 0 0 3 3"/>
                                                <path d="M18 5h2a3 3 0 0 1-3 3"/>
                                                <path d="M12 9v7"/>
                                                <path d="M8 21h8"/>
                                                <path d="M9.5 16h5"/>
                                            </svg>
                                        @endif

                                        <div>
                                            <div class="mobile-org-copy-title">{{ $row['title'] }}</div>
                                            <div class="mobile-org-copy-value">{{ $row['value'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
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