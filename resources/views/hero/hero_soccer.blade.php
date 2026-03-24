@php
    $user = $website->user;

    $primary   = $website->primary_color ?: '#0e4f86';
    $secondary = $website->secondary_color ?: '#061a4f';
    $accent    = $website->accent_color ?: '#ffffff';
    $bg        = $website->background_color ?: '#0b0b0b';
    $surface   = $website->surface_color ?: '#171717';
    $text1     = $website->text_primary_color ?: '#ffffff';
    $text2     = $website->text_secondary_color ?: '#dbeafe';

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

    $formatDateDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value));

        if ($date === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('F j, Y');
        } catch (\Throwable $e) {
            return $date;
        }
    };

    $formatBornYearDisplay = function ($value) use ($normalizeDisplayValue) {
        $date = trim($normalizeDisplayValue($value));

        if ($date === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y');
        } catch (\Throwable $e) {
            return $date;
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

$positionShorthandMap = [
    // basketball
    'point_guard' => 'Point Gd',
    'shooting_guard' => 'Shooting Gd',
    'small_forward' => 'Small Fwd',
    'power_forward' => 'Power Fwd',
    'center' => 'Center',

    // volleyball
    'setter' => 'Setter',
    'outside_hitter' => 'Outside Hit',
    'opposite_hitter' => 'Opp Hit',
    'middle_blocker' => 'Middle Block',
    'libero' => 'Libero',
    'defensive_specialist' => 'Def Specialist',

    // football
    'quarterback' => 'Quarterback',
    'running_back' => 'Running Back',
    'wide_receiver' => 'Wide Rec',
    'tight_end' => 'Tight End',
    'offensive_line' => 'Off Line',
    'defensive_line' => 'Def Line',
    'linebacker' => 'Linebacker',
    'cornerback' => 'Cornerback',
    'safety' => 'Safety',
    'kicker' => 'Kicker',
    'punter' => 'Punter',

    // baseball / softball
    'pitcher' => 'Pitcher',
    'catcher' => 'Catcher',
    'first_base' => '1st Base',
    'second_base' => '2nd Base',
    'third_base' => '3rd Base',
    'shortstop' => 'Shortstop',
    'left_field' => 'Left Field',
    'center_field' => 'Center Field',
    'right_field' => 'Right Field',
    'designated_hitter' => 'Desig Hit',

    // soccer
    'goalkeeper' => 'Goalkeeper',
    'defender' => 'Def',
    'center_back' => 'Center Back',
    'full_back' => 'Full Back',
    'wing_back' => 'Wing Back',
    'midfielder' => 'Mid',
    'defensive_midfielder' => 'Def Mid',
    'central_midfielder' => 'Central Mid',
    'attacking_midfielder' => 'Att Mid',
    'winger' => 'Winger',
    'forward' => 'Fwd',
    'striker' => 'Striker',

    // tennis / badminton / table tennis
    'singles' => 'Singles',
    'doubles' => 'Doubles',
    'mixed_doubles' => 'Mixed Doubles',

    // track and field
    'sprinter' => 'Sprinter',
    'middle_distance' => 'Mid Distance',
    'long_distance' => 'Long Distance',
    'hurdler' => 'Hurdler',
    'jumper' => 'Jumper',
    'thrower' => 'Thrower',
    'relay_runner' => 'Relay Runner',
    'decathlete' => 'Decathlete',
    'heptathlete' => 'Heptathlete',

    // swimming
    'freestyle' => 'Freestyle',
    'backstroke' => 'Backstroke',
    'breaststroke' => 'Breaststroke',
    'butterfly' => 'Butterfly',
    'individual_medley' => 'Ind Medley',
    'relay' => 'Relay',

    // boxing
    'flyweight' => 'Flyweight',
    'bantamweight' => 'Bantamweight',
    'featherweight' => 'Featherweight',
    'lightweight' => 'Lightweight',
    'welterweight' => 'Welterweight',
    'middleweight' => 'Middleweight',
    'light_heavyweight' => 'Light Heavy',
    'heavyweight' => 'Heavyweight',

    // martial arts
    'striker' => 'Striker',
    'grappler' => 'Grappler',
    'all_rounder' => 'All-Rounder',
];

    $formatPositionDisplay = function ($value) use ($normalizeDisplayValue, $positionShorthandMap) {
        $normalizePositionKey = function ($item) {
            return str((string) $item)
                ->trim()
                ->lower()
                ->replace('-', '_')
                ->replace(' ', '_')
                ->squish()
                ->replace('__', '_')
                ->toString();
        };

        $formatFallback = function ($item) {
            return str((string) $item)
                ->replace('_', ' ')
                ->replace('-', ' ')
                ->squish()
                ->title()
                ->toString();
        };

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return collect($value)
                ->filter()
                ->map(function ($item) use ($positionShorthandMap, $normalizePositionKey, $formatFallback) {
                    $key = $normalizePositionKey($item);
                    return $positionShorthandMap[$key] ?? $formatFallback($item);
                })
                ->implode(', ');
        }

        $position = trim($normalizeDisplayValue($value));

        if ($position === '') {
            return '';
        }

        $parts = preg_split('/\s*\/\s*|\s*,\s*/', $position) ?: [];

        return collect($parts)
            ->filter()
            ->map(function ($item) use ($positionShorthandMap, $normalizePositionKey, $formatFallback) {
                $key = $normalizePositionKey($item);
                return $positionShorthandMap[$key] ?? $formatFallback($item);
            })
            ->implode(', ');
    };

    $lightenHex = function ($hex, $percent = 25) {
        $hex = ltrim((string) $hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '#26b7d7';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = (int) round($r + ((255 - $r) * ($percent / 100)));
        $g = (int) round($g + ((255 - $g) * ($percent / 100)));
        $b = (int) round($b + ((255 - $b) * ($percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };

    $normalizeAccolades = function ($value) {
        if (blank($value)) {
            return collect();
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/', $trimmed) ?: [];
                $value = collect($lines)
                    ->flatMap(function ($line) {
                        return preg_split('/\s*\|\s*|\s*•\s*|\s*;\s*/', $line) ?: [];
                    })
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        if (is_array($value)) {
            return collect($value)
                ->flatMap(function ($item) {
                    if (is_array($item)) {
                        return [
                            $item['title'] ?? $item['name'] ?? $item['label'] ?? $item['value'] ?? null,
                        ];
                    }

                    return [$item];
                })
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values();
        }

        return collect();
    };

    $jerseyNumber = $normalizeDisplayValue($getHeroFieldValue('hero_jersey_number', $user?->jersey_number ?? ''));
    $firstName = trim($normalizeDisplayValue($getHeroFieldValue('hero_first_name', $user?->first_name ?? '')));
    $lastName = trim($normalizeDisplayValue($getHeroFieldValue('hero_last_name', $user?->last_name ?? '')));
    $position = $formatPositionDisplay($getHeroFieldValue('hero_position', $user?->position ?? ''));
    $dateOfBirth = $formatDateDisplay($getHeroFieldValue('hero_date_of_birth', $user?->birth ?? ''));
    $bornYear = $formatBornYearDisplay($getHeroFieldValue('hero_date_of_birth', $user?->birth ?? ''));
    $club = $normalizeDisplayValue($getHeroFieldValue('hero_club', $user?->club?->name ?? ''));
    $highSchool = $normalizeDisplayValue($getHeroFieldValue('hero_high_school', $user?->school?->name ?? ''));
    $gpa = $formatGpaDisplay($getHeroFieldValue('hero_gpa', $user?->gpa ?? ''));
    $coach = $normalizeDisplayValue($getHeroFieldValue('hero_coach', $user?->club_coach ?? ''));

    $sportAccolades = $normalizeAccolades(
        $getHeroFieldValue(
            'hero_sport_accolades',
            $user?->sport_accolades ?? $user?->sports_accolades ?? $user?->accolades ?? []
        )
    );

    $playerCardImageUrl = $resolveMediaUrl($user?->plyrcard_image, '');
    $mobileHeroImageUrl = $resolveMediaUrl($user?->mobile_hero_image, '');
    $playerImageUrl = $resolveMediaUrl($user?->player_image, '');
    $playerActionImageUrl = $resolveMediaUrl($getHeroFieldValue('hero_action_image'), '');

    $centerGradient = $lightenHex($primary, 24);
    $fullName = trim($firstName . ' ' . $lastName);

    $firstNameLength = mb_strlen(preg_replace('/\s+/', '', $firstName));
    $lastNameLength = mb_strlen(preg_replace('/\s+/', '', $lastName));
    $positionBesideLastName = $firstNameLength > $lastNameLength;

    $buildInstagramUrl = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return 'https://instagram.com/' . ltrim($value, '@');
    };

    $buildXUrl = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return 'https://x.com/' . ltrim($value, '@');
    };

    $igUrl = $buildInstagramUrl(
        $user?->instagram_url
        ?? $user?->instagram
        ?? $user?->ig_url
        ?? $user?->ig_handle
        ?? ''
    );

    $xUrl = $buildXUrl(
        $user?->x_url
        ?? $user?->twitter_url
        ?? $user?->twitter
        ?? $user?->x_handle
        ?? ''
    );

    $ytUrl = trim((string) ($user?->youtube_url ?? $user?->youtube ?? $user?->yt_url ?? ''));
    $playerEmail = trim((string) ($user?->email ?? ''));
    $hasAnySocial = filled($playerEmail) || filled($igUrl) || filled($ytUrl) || filled($xUrl);
@endphp
<style>
    :root{
        --hero-two-stage-max-width: 2600px;

        --hero-two-name-first: clamp(10.8rem, 10.05vw, 14.4rem);
        --hero-two-name-first-inline: clamp(11.0rem, 10.3vw, 14.65rem);
        --hero-two-name-last: clamp(8.65rem, 8.05vw, 10.8rem);
        --hero-two-front-number: clamp(8.65rem, 8.05vw, 10.8rem);

        --hero-two-back-number: clamp(28rem, 35vw, 46rem);
        --hero-two-position-size: clamp(2rem, 1.9vw, 2.55rem);

        --hero-two-panel-width: clamp(480px, 30vw, 640px);
        --hero-two-panel-font-size: clamp(16px, 1vw, 19px);

        --hero-two-social-size: clamp(27px, 1.75vw, 32px);
        --hero-two-social-icon-size: clamp(20px, 1.35vw, 24px);

        --hero-two-card-width: clamp(22rem, 18.5vw, 26rem);
        --hero-two-player-width: clamp(33rem, 29.5vw, 42rem);
        --hero-two-action-width: clamp(22rem, 19.5vw, 26rem);

        --hero-two-accolade-size: clamp(16px, .95vw, 19px);

        --hero-two-top-pad: clamp(2rem, 3vw, 3.2rem);
        --hero-two-content-bottom-space: 0px;
    }

    .hero-two-font-jersey-front {
        font-family: "Anton SC", "Antonio", "Anton", "Bebas Neue", Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
    }

    .hero-two-font-jersey-back {
        font-family: "Iceberg", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
    }

    .hero-two-font-name {
        font-family: "Anton SC", "Antonio", "Anton", "Bebas Neue", Arial, sans-serif;
        font-feature-settings: "calt" 0, "liga" 0;
        font-weight: 400;
    }

    .hero-two-font-sans {
        font-family: "Poppins", "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .hero-two-desktop { display:block; }
    .hero-two-mobile { display:none; }

    /* Keep same structure for tablet. Mobile only on small screens. */
    @media (max-width: 767px) {
        .hero-two-desktop { display:none; }
        .hero-two-mobile { display:block; }
    }

    .hero-two-hero.hero-two-has-accolades {
        --hero-two-content-bottom-space: clamp(80px, 9vh, 150px);
    }

    .hero-two-hero {
        position: relative;
        overflow: hidden;
        min-height: 100svh;
        height: auto;
        padding-top: var(--hero-two-top-pad);
    }

    .hero-two-shell {
        position: relative;
        width: 100%;
        max-width: var(--hero-two-stage-max-width);
        margin: 0 auto;
        min-height: calc(100svh - var(--hero-two-top-pad));
    }

    .hero-two-stage {
        position: relative;
        width: 100%;
        min-height: calc(100svh - var(--hero-two-top-pad));
    }

    .hero-two-shadow {
        filter: drop-shadow(0 18px 30px rgba(0,0,0,.24));
    }

    .hero-two-card-shadow {
        filter: drop-shadow(0 14px 28px rgba(0,0,0,.22));
    }

    .hero-two-name-line {
        line-height: .84;
        white-space: nowrap;
    }

    .hero-two-left-group {
        position: relative;
        z-index: 20;
        width: min(50.5%, 760px);
        margin-left: 3.2%;
        padding-bottom: var(--hero-two-content-bottom-space);
    }

    .hero-two-front-jersey {
        font-size: var(--hero-two-front-number);
        line-height: 1;
        letter-spacing: -.04em;
    }

    .hero-two-name-first {
        font-size: var(--hero-two-name-first);
    }

    .hero-two-name-first.has-inline-position {
        font-size: var(--hero-two-name-first-inline);
    }

    .hero-two-name-last {
        font-size: var(--hero-two-name-last);
    }

    .hero-two-position {
        white-space: nowrap;
        line-height: 1;
        color: rgba(255,255,255,.95);
        font-size: var(--hero-two-position-size);
    }

    .hero-two-back-jersey {
        position: absolute;
        left: 60%;
        top: 35.5%;
        transform: translate(-50%, -50%);
        z-index: 1;
        font-size: var(--hero-two-back-number);
        line-height: .88;
        font-weight:800;
        letter-spacing: -.05em;
        color: rgba(255,255,255,.16);
        pointer-events: none;
    }

    .hero-two-accolades-wrap {
        width: var(--hero-two-panel-width);
        margin-top: 1rem;
        margin-bottom: .8rem;
    }

    .hero-two-accolades-list {
        display: flex;
        flex-direction: column;
        gap: .4rem;
    }

    .hero-two-accolade-item {
        display: flex;
        align-items: center;
        gap: .62rem;
        color: rgba(255,255,255,.98);
        font-size: var(--hero-two-accolade-size);
        line-height: 1.2;
        font-weight: 500;
        text-shadow: 0 6px 18px rgba(0,0,0,.18);
    }

    .hero-two-accolade-icon {
        width: 1.65em;
        height: 1.65em;
        flex: 0 0 1.65em;
        color: rgba(255, 255, 0, 0.98);
        display: block;
    }

    .hero-two-accolade-icon,
    .hero-two-accolade-icon * {
        fill: currentColor !important;
        stroke: none !important;
    }

    .hero-two-info-wrap {
        margin-top: 1rem;
        position: relative;
        z-index: 25;
    }

    .hero-two-info-panel {
        position: relative;
        width: var(--hero-two-panel-width);
        border: 1px solid rgba(255,255,255,.10);
        border-radius: 18px;
        padding: 1.2rem 1.35rem 1.2rem;
        background: linear-gradient(
            90deg,
            rgba(11,73,154,.82) 0%,
            rgba(20,97,182,.63) 45%,
            rgba(17,69,145,.54) 100%
        );
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        box-shadow: 0 16px 34px rgba(0,0,0,.18);
    }

    .hero-two-social-floating {
        position: absolute;
        top: .85rem;
        right: .9rem;
        z-index: 80;
        display: flex;
        align-items: center;
        gap: 10px;
        pointer-events: auto;
    }

    .hero-two-social-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--hero-two-social-size);
        height: var(--hero-two-social-size);
        color: rgba(255,255,255,.92);
        text-decoration: none;
        transition: opacity .2s ease, transform .2s ease;
        flex: 0 0 auto;
    }

    .hero-two-social-link:hover {
        opacity: 1;
        transform: translateY(-1px);
    }

    .hero-two-social-link.is-disabled {
        opacity: .35;
        pointer-events: none;
    }

    .hero-two-social-link svg {
        width: var(--hero-two-social-icon-size);
        height: var(--hero-two-social-icon-size);
        display: block;
    }

    .hero-two-stat-row {
        display: flex;
        align-items: flex-start;
        gap: .8rem;
    }

    .hero-two-stat-block {
        display: block;
    }

    .hero-two-stat-block .hero-two-stat-label {
        display: block;
        margin-bottom: .28rem;
    }

    .hero-two-stat-block .hero-two-stat-value {
        display: block;
        width: 100%;
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .hero-two-stat-label,
    .hero-two-stat-value {
        font-size: var(--hero-two-panel-font-size);
        line-height: 1.24;
        color: rgba(255,255,255,.98);
    }

    .hero-two-stat-label {
        font-weight: 800;
        text-transform: uppercase;
        flex: 0 0 auto;
        letter-spacing: .01em;
    }

    .hero-two-stat-value {
        font-weight: 500;
        flex: 1 1 auto;
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .hero-two-card-wrap {
        position: absolute;
        right: 1.1%;
        top: 2.8%;
        z-index: 26;
    }

    .hero-two-card {
        width: var(--hero-two-card-width);
        object-fit: contain;
    }

    .hero-two-player-wrap {
        position: absolute;
        right: 21%;
        bottom: 0;
        z-index: 14;
    }

    .hero-two-player-image {
        width: var(--hero-two-player-width);
        max-width: 100%;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .hero-two-action-wrap {
        position: absolute;
        left: calc(3.2% + var(--hero-two-panel-width) - 2.2rem);
        bottom: 0;
        z-index: 28;
        pointer-events: none;
    }

    .hero-two-action-image {
        width: var(--hero-two-action-width);
        max-width: none;
        height: auto;
        margin-bottom:-80px;
        object-fit: contain;
        display: block;
    }

    @media (max-width: 1536px) {
        :root{
            --hero-two-name-first: clamp(8.9rem, 8.25vw, 11.9rem);
            --hero-two-name-first-inline: clamp(9.1rem, 8.5vw, 12.1rem);
            --hero-two-name-last: clamp(7.15rem, 6.6vw, 9.6rem);
            --hero-two-front-number: clamp(7.15rem, 6.6vw, 9.6rem);

            --hero-two-back-number: clamp(25rem, 35vw, 45rem);
            --hero-two-position-size: clamp(1.8rem, 1.65vw, 2.2rem);

            --hero-two-panel-width: clamp(450px, 30vw, 600px);
            --hero-two-panel-font-size: clamp(15px, .96vw, 18px);

            --hero-two-card-width: clamp(19.5rem, 16.5vw, 23rem);
            --hero-two-player-width: clamp(30rem, 27vw, 38rem);
            --hero-two-action-width: clamp(18.5rem, 16.5vw, 22rem);
        }

        .hero-two-player-wrap { right: 21%; bottom: 0; }
        .hero-two-card-wrap { right: 1%; }
        .hero-two-back-jersey { left: 60%; top: 35%; }
        .hero-two-action-wrap { left: calc(3.2% + var(--hero-two-panel-width) - 1.8rem); bottom: 0; }
    }

    @media (max-width: 1280px) {
        :root{
            --hero-two-name-first: clamp(7.3rem, 6.8vw, 9.6rem);
            --hero-two-name-first-inline: clamp(7.45rem, 7vw, 9.8rem);
            --hero-two-name-last: clamp(5.75rem, 5.35vw, 7.3rem);
            --hero-two-front-number: clamp(5.75rem, 5.35vw, 7.3rem);

            --hero-two-back-number: clamp(21rem, 35vw, 32rem);
            --hero-two-position-size: clamp(1.45rem, 1.35vw, 1.8rem);

            --hero-two-panel-width: clamp(390px, 28vw, 520px);
            --hero-two-panel-font-size: clamp(14px, .92vw, 16px);

            --hero-two-card-width: clamp(16rem, 15vw, 19rem);
            --hero-two-player-width: clamp(25rem, 23vw, 31rem);
            --hero-two-action-width: clamp(14.5rem, 12.5vw, 17rem);
        }

        .hero-two-left-group { width: 50%; }
        .hero-two-player-wrap { right: 21%; bottom: 0; }
        .hero-two-card-wrap { right: .8%; top: 3.2%; }
        .hero-two-info-panel { padding: 1.05rem 1.15rem 1.05rem; }
        .hero-two-action-wrap { left: calc(3.2% + var(--hero-two-panel-width) - 1.2rem); bottom: 0; }
        .hero-two-back-jersey { left: 60%; top: 39.5%; }
    }

    /* Tablet view: keep same structure, just scale it down */
    @media (max-width: 1023px) and (min-width: 768px) {
        :root{
            --hero-two-name-first: clamp(5.6rem, 7.5vw, 7.2rem);
            --hero-two-name-first-inline: clamp(5.8rem, 7.7vw, 7.4rem);
            --hero-two-name-last: clamp(4.45rem, 5.9vw, 5.8rem);
            --hero-two-front-number: clamp(4.45rem, 5.9vw, 5.8rem);

            --hero-two-back-number: clamp(16rem, 35vw, 22rem);
            --hero-two-position-size: clamp(1.2rem, 1.7vw, 1.5rem);

            --hero-two-panel-width: clamp(330px, 40vw, 400px);
            --hero-two-panel-font-size: clamp(12px, 1.4vw, 14px);

            --hero-two-card-width: clamp(12rem, 17vw, 14rem);
            --hero-two-player-width: clamp(20rem, 29vw, 24rem);
            --hero-two-action-width: clamp(11rem, 15vw, 13rem);

            --hero-two-accolade-size: clamp(12px, 1.4vw, 14px);
            --hero-two-content-bottom-space: 0px;
        }

        .hero-two-hero.hero-two-has-accolades {
            --hero-two-content-bottom-space: clamp(56px, 8vh, 100px);
        }

        .hero-two-left-group {
            width: min(54%, 440px);
            margin-left: 2.4%;
        }

        .hero-two-position {
            white-space: normal;
        }

        .hero-two-accolades-wrap {
            width: var(--hero-two-panel-width);
            margin-top: .7rem;
            margin-bottom: .6rem;
        }

        .hero-two-info-wrap {
            margin-top: .75rem;
        }

        .hero-two-info-panel {
            border-radius: 14px;
            padding: .95rem 1rem .95rem;
        }

        .hero-two-social-floating {
            top: .65rem;
            right: .7rem;
            gap: 8px;
        }

        .hero-two-stat-row {
            gap: .55rem;
        }

        .hero-two-card-wrap {
            right: 1.5%;
            top: 2.2%;
        }

        .hero-two-player-wrap {
            right: 21%;
            bottom: 0;
        }

        .hero-two-action-wrap {
            left: calc(2.4% + var(--hero-two-panel-width) - .6rem);
            bottom: 0;
        }

        .hero-two-back-jersey {
            left: 60%;
            top: 41%;
        }
    }

    @media (min-width: 1800px) {
        :root{
            --hero-two-name-first: clamp(11.5rem, 10.6vw, 15.45rem);
            --hero-two-name-first-inline: clamp(11.7rem, 10.85vw, 15.7rem);
            --hero-two-name-last: clamp(9.25rem, 8.55vw, 11.55rem);
            --hero-two-front-number: clamp(9.25rem, 8.55vw, 11.55rem);

            --hero-two-back-number: clamp(31rem, 29vw, 48rem);
            --hero-two-position-size: clamp(2.15rem, 1.95vw, 2.7rem);

            --hero-two-panel-width: clamp(560px, 32vw, 760px);
            --hero-two-panel-font-size: clamp(17px, 1.08vw, 21px);

            --hero-two-card-width: clamp(23rem, 18.8vw, 27rem);
            --hero-two-player-width: clamp(35rem, 29vw, 43rem);
            --hero-two-action-width: clamp(23rem, 19.5vw, 27rem);
        }

        .hero-two-player-wrap { right: 21%; bottom: 0; }
        .hero-two-card-wrap { right: 1.8%; }
        .hero-two-back-jersey { left: 60%; top: 41.2%; }
        .hero-two-action-wrap { left: calc(3.2% + var(--hero-two-panel-width) - 2.4rem); bottom: 0; }
    }
</style>

<section
    class="hero-two-desktop hero-two-hero {{ $sportAccolades->isNotEmpty() ? 'hero-two-has-accolades' : '' }} z-0"
    style="background:
        radial-gradient(circle at center, {{ $centerGradient }} 0%, {{ $primary }} 48%, {{ $secondary }} 100%);
        color: {{ $text1 }};"
>
    <div class="absolute inset-0 z-0 pointer-events-none">
        <div class="absolute inset-x-0 bottom-0 h-[34%]" style="background: linear-gradient(to top, rgba(0,0,0,.24), rgba(0,0,0,0));"></div>
        <div class="absolute inset-y-0 left-0 w-[16%]" style="background: linear-gradient(to right, rgba(0, 12, 70, .44), rgba(0, 12, 70, 0));"></div>
        <div class="absolute inset-y-0 right-0 w-[16%]" style="background: linear-gradient(to left, rgba(0, 12, 70, .34), rgba(0, 12, 70, 0));"></div>
    </div>

    <div class="hero-two-shell">
        <div class="hero-two-stage">
            @if ($jerseyNumber)
                <div class="hero-two-font-jersey-back hero-two-back-jersey">
                    {{ $jerseyNumber }}
                </div>
            @endif

            @if ($playerCardImageUrl)
                <div class="hero-two-card-wrap">
                    <img
                        src="{{ $playerCardImageUrl }}"
                        alt="Player card"
                        class="hero-two-card-shadow hero-two-card"
                    />
                </div>
            @endif

            <div class="hero-two-left-group">
                @if ($jerseyNumber)
                    <div class="hero-two-font-jersey-front hero-two-front-jersey text-white">
                        #{{ $jerseyNumber }}
                    </div>
                @endif

                <div>
                    @if ($firstName)
                        <div class="flex items-end gap-3">
                            <div class="hero-two-font-name hero-two-name-line hero-two-name-first text-white {{ ! $positionBesideLastName && $position ? 'has-inline-position' : '' }}">
                                {{ $firstName }}
                            </div>

                            @if ($position && ! $positionBesideLastName)
                                <div class="hero-two-font-sans hero-two-position mb-[0.35rem]">
                                    {{ $position }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-end gap-3 mt-1">
                        @if ($lastName)
                            <div class="hero-two-font-name hero-two-name-line hero-two-name-last text-white">
                                {{ $lastName }}
                            </div>
                        @endif

                        @if ($position && $positionBesideLastName)
                            <div class="hero-two-font-sans hero-two-position mb-[0.32rem]">
                                {{ $position }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="hero-two-info-wrap">
                    @if ($sportAccolades->isNotEmpty())
                        <div class="hero-two-accolades-wrap hero-two-font-sans">
                            <div class="hero-two-accolades-list">
                                @foreach ($sportAccolades as $accolade)
                                    <div class="hero-two-accolade-item">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="hero-two-accolade-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M17 3H7v2H3v2c0 2.97 2.16 5.43 5 5.91.2.71.57 1.36 1.07 1.91.55.6 1.24 1.03 1.93 1.28V19H8v2h8v-2h-3v-2.9c.69-.25 1.38-.68 1.93-1.28.5-.55.87-1.2 1.07-1.91 2.84-.48 5-2.94 5-5.91V5h-4V3ZM5 7V7h2v.18c0 1.16.19 2.25.54 3.23C6.09 9.92 5 8.58 5 7Zm14 0c0 1.58-1.09 2.92-2.54 3.41.35-.98.54-2.07.54-3.23V7h2Z"/>
                                        </svg>
                                        <strong>{{ $accolade }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="hero-two-info-panel">
                        @if ($hasAnySocial)
                            <div class="hero-two-social-floating">
                                <a href="{{ $playerEmail ? 'mailto:' . $playerEmail : '#' }}"
                                   class="hero-two-social-link {{ empty($playerEmail) ? 'is-disabled' : '' }}"
                                   aria-label="Email">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 5.5h18v13H3z"></path>
                                        <path d="m4 7 8 6 8-6"></path>
                                    </svg>
                                </a>

                                <a href="{{ $igUrl ?: '#' }}"
                                   class="hero-two-social-link {{ empty($igUrl) ? 'is-disabled' : '' }}"
                                   aria-label="Instagram"
                                   target="{{ !empty($igUrl) ? '_blank' : '_self' }}"
                                   rel="{{ !empty($igUrl) ? 'noopener noreferrer' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2Zm8.5 1.8h-8.5A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95ZM12 7.1A4.9 4.9 0 1 1 7.1 12 4.91 4.91 0 0 1 12 7.1Zm0 1.8A3.1 3.1 0 1 0 15.1 12 3.1 3.1 0 0 0 12 8.9Zm5.15-2.3a1.2 1.2 0 1 1-1.2 1.2 1.2 1.2 0 0 1 1.2-1.2Z"/>
                                    </svg>
                                </a>

                                <a href="{{ $ytUrl ?: '#' }}"
                                   class="hero-two-social-link {{ empty($ytUrl) ? 'is-disabled' : '' }}"
                                   aria-label="YouTube"
                                   target="{{ !empty($ytUrl) ? '_blank' : '_self' }}"
                                   rel="{{ !empty($ytUrl) ? 'noopener noreferrer' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.4.6A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.8.6 9.4.6 9.4.6s7.6 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8ZM9.8 15.5v-7l6.2 3.5-6.2 3.5Z"/>
                                    </svg>
                                </a>

                                <a href="{{ $xUrl ?: '#' }}"
                                   class="hero-two-social-link {{ empty($xUrl) ? 'is-disabled' : '' }}"
                                   aria-label="X"
                                   target="{{ !empty($xUrl) ? '_blank' : '_self' }}"
                                   rel="{{ !empty($xUrl) ? 'noopener noreferrer' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                        <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/>
                                    </svg>
                                </a>
                            </div>
                        @endif

                        <div class="hero-two-font-sans space-y-2">
                            <div class="hero-two-stat-row {{ $hasAnySocial ? 'pr-[105px]' : '' }}">
                                <div class="hero-two-stat-label">Full Name</div>
                                <div class="hero-two-stat-value">{{ $fullName }}</div>
                            </div>

                            @if ($bornYear || $dateOfBirth)
                                <div class="hero-two-stat-row">
                                    <div class="hero-two-stat-label">Born</div>
                                    <div class="hero-two-stat-value">{{ $bornYear ?: $dateOfBirth }}</div>
                                </div>
                            @endif

                            @if ($club)
                                <div class="hero-two-stat-row">
                                    <div class="hero-two-stat-label">Club</div>
                                    <div class="hero-two-stat-value">{{ $club }}</div>
                                </div>
                            @endif

                            @if ($highSchool || $gpa)
                                <div class="hero-two-stat-block">
                                    <div class="hero-two-stat-label">High School</div>
                                    <div class="hero-two-stat-value">
                                        @if ($highSchool && $gpa)
                                            {{ $highSchool }} - GPA {{ $gpa }}
                                        @elseif ($highSchool)
                                            {{ $highSchool }}
                                        @else
                                            GPA {{ $gpa }}
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($coach)
                                <div class="hero-two-stat-block">
                                    <div class="hero-two-stat-label">Coach</div>
                                    <div class="hero-two-stat-value uppercase font-semibold tracking-[0.02em]">{{ $coach }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($playerImageUrl)
                <div class="hero-two-player-wrap">
                    <img
                        src="{{ $playerImageUrl }}"
                        alt="{{ $fullName }}"
                        class="hero-two-shadow hero-two-player-image"
                    />
                </div>
            @endif

            @if ($playerActionImageUrl)
                <div class="hero-two-action-wrap">
                    <img
                        src="{{ $playerActionImageUrl }}"
                        alt="Player action image"
                        class="hero-two-shadow hero-two-action-image"
                    />
                </div>
            @endif
        </div>
    </div>
</section>

<section
    class="hero-two-mobile relative overflow-hidden h-[100svh] min-h-[100svh]"
    style="background: {{ $primary }};"
>
    @if ($mobileHeroImageUrl)
        <img
            src="{{ $mobileHeroImageUrl }}"
            alt="Mobile hero"
            class="absolute inset-0 block w-full h-full object-cover"
        />
    @endif
</section>